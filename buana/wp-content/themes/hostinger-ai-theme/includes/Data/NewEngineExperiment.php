<?php

namespace Hostinger\AiTheme\Data;

use Hostinger\Amplitude\AmplitudeManager;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * AI website creation v2 AB test
 * https://app.amplitude.com/experiment/hostinger/404652/config/779242/settings
 */
class NewEngineExperiment {
    public const OPTION = 'hostinger_ai_theme_new_engine_experiment_v2';

    private const EXPERIMENTS_ENDPOINT = '/v3/wordpress/amplitude/experiments';
    private const EXPERIMENT_KEY       = 'wordpress-ai-engine-v2';
    private const ENROLLED_VARIANT     = '1';
    private const EXPOSURE_EVENT       = '$exposure';
    private const REQUEST_TIMEOUT      = 5;

    private Client $client;
    private Utils $utils;
    private AmplitudeManager $amplitude_manager;

    public function __construct( Client $client, Utils $utils, AmplitudeManager $amplitude_manager ) {
        $this->client            = $client;
        $this->utils             = $utils;
        $this->amplitude_manager = $amplitude_manager;
    }

    public static function is_enabled(): bool {
        return (bool) get_option( self::OPTION );
    }

    public static function is_resolved(): bool {
        return get_option( self::OPTION, null ) !== null;
    }

    public static function mark_enabled(): void {
        update_option( self::OPTION, 1 );
    }

    public static function mark_disabled(): void {
        update_option( self::OPTION, 0 );
    }

    public function register(): void {
        add_action( 'admin_init', array( $this, 'resolve' ), 1 );
    }

    public function resolve(): void {
        if ( wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return;
        }

	    // The ecommerce flow always uses the new engine.
	    if ( HostingerEcommerceHelper::is_active() ) {
		    self::mark_enabled();
		    return;
	    }

        if ( self::is_resolved() ) {
            return;
        }

        $data = $this->fetch_experiments();

        if ( $data === null ) {
            self::mark_disabled();
            return;
        }

        $variant = $data[ self::EXPERIMENT_KEY ]['key'] ?? null;

        if ( $variant === null ) {
            self::mark_disabled();
            return;
        }

        if ( $variant === self::ENROLLED_VARIANT ) {
            self::mark_enabled();
        } else {
            self::mark_disabled();
        }


        $this->send_exposure( $variant );
    }

    private function send_exposure( string $variant ): void {
        $this->amplitude_manager->sendRequest(
            AmplitudeManager::AMPLITUDE_ENDPOINT,
            array(
                'action'   => self::EXPOSURE_EVENT,
                'flag_key' => self::EXPERIMENT_KEY,
                'variant'  => $variant,
            )
        );
    }

    private function fetch_experiments(): ?array {
        try {
            $response = $this->client->get(
                self::EXPERIMENTS_ENDPOINT,
                array( 'domain' => $this->utils->getHostInfo() ),
                array(),
                self::REQUEST_TIMEOUT
            );

            if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
                return null;
            }

            $body = wp_remote_retrieve_body( $response );

            if ( empty( $body ) ) {
                return null;
            }

            $json = json_decode( $body, true );

            if ( empty( $json['data'] ) || ! is_array( $json['data'] ) ) {
                return null;
            }

            return $json['data'];
        } catch ( \Exception $exception ) {
            $this->utils->errorLog( 'NewEngineExperiment request error: ' . $exception->getMessage() );
            return null;
        }
    }
}
