<?php

namespace Hostinger\AiAssistant\Nudges;

use Hostinger\AiAssistant\Chatbot\ReachOutClient;
use Hostinger\AiAssistant\Nudges\Dto\ReachOut;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Registers the single `/nudges` REST endpoint and evaluates every registered
 * nudge on request. The browser posts its chatbot user_id (only it knows the
 * widget's localStorage id); the server evaluates conditions and dispatches
 * reach-outs.
 */
class NudgeManager {
    private const GLOBAL_COOLDOWN_TTL = DAY_IN_SECONDS;

    private const GLOBAL_COOLDOWN_KEY = 'hostinger_ai_nudge_global_cooldown';

    private const GLOBAL_COOLDOWN_OPTION = 'hostinger_ai_nudge_global_cooldown_at';

    /**
     * @var NudgeInterface[]
     */
    private array $nudges = array();

    public function __construct(
        private ReachOutClient $reach_out_client
    ) {
    }

    public function register( NudgeInterface $nudge ): void {
        $this->nudges[] = $nudge;
    }

    /**
     * Registered nudges ordered by priority (highest first). PHP's sort is
     * stable, so equal priorities keep their registration order.
     *
     * @return NudgeInterface[]
     */
    private function sorted_nudges(): array {
        $nudges = $this->nudges;

        usort(
            $nudges,
            static fn( NudgeInterface $a, NudgeInterface $b ): int => $b->get_priority() <=> $a->get_priority()
        );

        return $nudges;
    }

    public function init(): void {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function register_rest_routes(): void {
        register_rest_route(
            HOSTINGER_AI_ASSISTANT_REST_API_BASE,
            '/nudges',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_request' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'user_id' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            HOSTINGER_AI_ASSISTANT_REST_API_BASE,
            '/nudges/dismiss',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_dismiss' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'campaign' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );
    }

    public function permission_check(): bool {
        return is_user_logged_in() && current_user_can( 'manage_options' );
    }

    public function handle_request( WP_REST_Request $request ): WP_REST_Response {
        $user_id = (string) $request->get_param( 'user_id' );

        if ( empty( $user_id ) ) {
            return new WP_REST_Response( array( 'data' => array( 'results' => array() ) ), WP_Http::BAD_REQUEST );
        }

        $results = array();

        foreach ( $this->nudges as $nudge ) {
            $nudge->touch_relevance();
        }

        // While a nudge is on its global cooldown, suppress every nudge so two
        // different ones never stack up on the user.
        if ( $this->in_global_cooldown() ) {
            foreach ( $this->nudges as $nudge ) {
                $results[ $nudge->get_name() ] = 'cooldown';
            }

            return new WP_REST_Response( array( 'data' => array( 'results' => $results ) ), WP_Http::OK );
        }

        /**
         * The first nudge that passes evaluation, keyed by campaign name. Only one
         * reach-out is dispatched per request (first-wins by priority, then
         * registration order), so nudges never compete for the same conversation.
         *
         * @var array<string, array{nudge: NudgeInterface, reach_out: ReachOut}> $pending
         */
        $pending = array();

        foreach ( $this->sorted_nudges() as $nudge ) {
            $name = $nudge->get_name();

            if ( ! $nudge->is_enabled() ) {
                $results[ $name ] = 'disabled';
                continue;
            }

            if ( $nudge->is_throttled() ) {
                $results[ $name ] = 'throttled';
                continue;
            }
            $nudge->touch_throttle();

            $reach_out = $nudge->evaluate();
            if ( $reach_out === null ) {
                $results[ $name ] = 'skipped';
                continue;
            }

            $pending[ $name ] = array(
                'nudge'     => $nudge,
                'reach_out' => $reach_out,
            );

            break;
        }

        $results += $this->dispatch( $user_id, $pending );

        return new WP_REST_Response( array( 'data' => array( 'results' => $results ) ), WP_Http::OK );
    }

    public function handle_dismiss( WP_REST_Request $request ): WP_REST_Response {
        $campaign = (string) $request->get_param( 'campaign' );

        foreach ( $this->nudges as $nudge ) {
            if ( $nudge->get_name() === $campaign ) {
                $nudge->mark_dismissed();

                return new WP_REST_Response( array( 'data' => array( 'dismissed' => true ) ), WP_Http::OK );
            }
        }

        return new WP_REST_Response( array( 'data' => array( 'dismissed' => false ) ), WP_Http::BAD_REQUEST );
    }

    private function in_global_cooldown(): bool {
        if ( (bool) get_transient( self::GLOBAL_COOLDOWN_KEY ) ) {
            return true;
        }

        $started_at = (int) get_option( self::GLOBAL_COOLDOWN_OPTION, 0 );

        return $started_at > 0 && ( time() - $started_at ) < self::GLOBAL_COOLDOWN_TTL;
    }

    private function start_global_cooldown(): void {
        update_option( self::GLOBAL_COOLDOWN_OPTION, time() );
    }

    /**
     * Send every fired reach-out in one batch and persist state for the accepted ones.
     *
     * @param array<string, array{nudge: NudgeInterface, reach_out: ReachOut}> $pending
     *
     * @return array<string, string> Campaign => 'sent' | 'send_failed'.
     */
    private function dispatch( string $user_id, array $pending ): array {
        if ( empty( $pending ) ) {
            return array();
        }

        $reach_outs = array();
        foreach ( $pending as $name => $item ) {
            $reach_outs[ $name ] = array(
                'message' => $item['reach_out']->get_message(),
                'visuals' => $item['reach_out']->get_visuals(),
            );
        }

        $outcomes = $this->reach_out_client->send_batch( $user_id, $reach_outs );

        $results = array();
        foreach ( $pending as $name => $item ) {
            if ( $outcomes[ $name ] ?? false ) {
                $item['nudge']->mark_sent( $item['reach_out'] );
                $this->start_global_cooldown();
                $results[ $name ] = 'sent';
            } else {
                $results[ $name ] = 'send_failed';
            }
        }

        return $results;
    }
}
