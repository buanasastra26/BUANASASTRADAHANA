<?php

namespace Hostinger\AiAssistant\Nudges;

use Hostinger\AiAssistant\Hosting\DomainRepository;
use Hostinger\AiAssistant\Hosting\HpanelUrlBuilder;
use Hostinger\AiAssistant\Nudges\Dto\ReachOut;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class DomainRenewalNudge extends AbstractNudge {
    private const CAMPAIGN_NAME      = 'wordpress-renew-domain';
    private const EXPIRY_WINDOW_DAYS = 30;

    public function __construct(
        private DomainRepository $domain_repository,
        private HpanelUrlBuilder $url_builder
    ) {
    }

    public function get_name(): string {
        return self::CAMPAIGN_NAME;
    }

    public function get_priority(): int {
        return 90;
    }

    protected function build_reach_out(): ?ReachOut {
        $domain_details = $this->domain_repository->get_domain_details();

        if ( $domain_details === null ) {
            return null;
        }

        $expires_ts = $this->get_expiration_timestamp( $domain_details );
        if ( ! $expires_ts ) {
            return null;
        }

        $days_left = ( $expires_ts - time() ) / DAY_IN_SECONDS;

        if ( $days_left <= 0 || $days_left > self::EXPIRY_WINDOW_DAYS ) {
            return null;
        }

        $dedup_key = (string) $expires_ts;

        $domain      = isset( $domain_details['domain'] ) ? $this->url_builder->normalize_domain( (string) $domain_details['domain'] ) : '';
        $renewal_url = $this->url_builder->domain_renewal_url( $domain );

        return new ReachOut(
            $this->build_message( $expires_ts ),
            $dedup_key,
            $this->build_renewal_button( $renewal_url )
        );
    }

    private function get_expiration_timestamp( array $domain_details ): int {
        $expires_at = $domain_details['expiresAt'] ?? ( $domain_details['expires_at'] ?? '' );

        if ( empty( $expires_at ) ) {
            return 0;
        }

        $timestamp = strtotime( (string) $expires_at );

        return $timestamp ? $timestamp : 0;
    }

    private function build_message( int $expires_ts ): string {
        return sprintf(
            /* translators: %s: domain expiration date. */
            __( 'Hey! Just a heads-up — your domain is expiring on %s. Renew it now to keep your website running without interruption.', 'hostinger-ai-assistant' ),
            date_i18n( get_option( 'date_format' ), $expires_ts )
        );
    }

    private function build_renewal_button( string $renewal_url ): array {
        return array(
            array(
                'id'   => 'renew-domain',
                'type' => 'button',
                'data' => array(
                    'display_text' => __( 'Renew my domain', 'hostinger-ai-assistant' ),
                    'url'          => esc_url_raw( $renewal_url ),
                ),
            ),
        );
    }
}
