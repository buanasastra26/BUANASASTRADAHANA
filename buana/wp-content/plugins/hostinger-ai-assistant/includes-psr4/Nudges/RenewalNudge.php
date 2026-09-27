<?php

namespace Hostinger\AiAssistant\Nudges;

use Hostinger\AiAssistant\Hosting\HpanelUrlBuilder;
use Hostinger\AiAssistant\Hosting\PlanRepository;
use Hostinger\AiAssistant\Nudges\Dto\ReachOut;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Reminds the user to renew their hosting plan when it is within the expiry
 * window. Reuses the Easy Onboarding plan-details data and Hostinger dashboard
 * renewal URL.
 */
class RenewalNudge extends AbstractNudge {
    private const CAMPAIGN_NAME      = 'wordpress-renew-hosting-plan';
    private const EXPIRY_WINDOW_DAYS = 14;

    public function __construct(
        private PlanRepository $plan_repository,
        private HpanelUrlBuilder $url_builder
    ) {
    }

    public function get_name(): string {
        return self::CAMPAIGN_NAME;
    }

    public function get_priority(): int {
        return 100;
    }

    protected function build_reach_out(): ?ReachOut {
        $plan = $this->plan_repository->get_plan_details();

        if ( $plan === null ) {
            return null;
        }

        $expires_ts = $this->get_expiration_timestamp( $plan );
        if ( ! $expires_ts ) {
            return null;
        }

        $days_left = ( $expires_ts - time() ) / DAY_IN_SECONDS;

        if ( $days_left <= 0 || $days_left > self::EXPIRY_WINDOW_DAYS ) {
            return null;
        }

        $dedup_key = (string) $expires_ts;

        $domain      = isset( $plan['original_domain'] ) ? $this->url_builder->normalize_domain( (string) $plan['original_domain'] ) : '';
        $renewal_url = $this->url_builder->hosting_renewal_url( $domain );

        return new ReachOut(
            $this->build_message( $expires_ts ),
            $dedup_key,
            $this->build_renewal_button( $renewal_url )
        );
    }

    private function get_expiration_timestamp( array $plan ): int {
        $expires_at = $plan['hosting']['expires_at'] ?? ( $plan['hosting']['expiresAt'] ?? '' );

        if ( empty( $expires_at ) ) {
            return 0;
        }

        $timestamp = strtotime( (string) $expires_at );

        return $timestamp ? $timestamp : 0;
    }

    private function build_message( int $expires_ts ): string {
        return sprintf(
            /* translators: %s: hosting plan expiration date. */
            __( 'Hey! Just a heads-up — your hosting plan is expiring on %s. Renew it now to keep your website running without interruption.', 'hostinger-ai-assistant' ),
            date_i18n( get_option( 'date_format' ), $expires_ts )
        );
    }

    /**
     * The "Renew my plan" CTA is sent as a reach-out visual (a button), which the
     * chatbot backend maps to the widget's built-in `webhookVisual` renderer. The
     * `display_text`/`url` keys match the reach-out webhook contract agreed with
     * the chatbots team.
     *
     * @return array<int, array<string, mixed>>
     */
    private function build_renewal_button( string $renewal_url ): array {
        return array(
            array(
                'id'   => 'renew-hosting-plan',
                'type' => 'button',
                'data' => array(
                    'display_text' => __( 'Renew my plan', 'hostinger-ai-assistant' ),
                    'url'          => esc_url_raw( $renewal_url ),
                ),
            ),
        );
    }
}
