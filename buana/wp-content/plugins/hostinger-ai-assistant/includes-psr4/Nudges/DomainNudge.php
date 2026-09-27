<?php

namespace Hostinger\AiAssistant\Nudges;

use Hostinger\AiAssistant\Hosting\HpanelUrlBuilder;
use Hostinger\AiAssistant\Hosting\SiteUrlRepository;
use Hostinger\AiAssistant\Nudges\Dto\ReachOut;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class DomainNudge extends AbstractNudge {
    private const CAMPAIGN_NAME       = 'wordpress-connect-domain';
    protected const MIN_SITE_AGE_DAYS = 14;
    private const DEDUP_KEY           = 'temporary-domain';

    public function __construct(
        private HpanelUrlBuilder $url_builder,
        private SiteUrlRepository $site_url_repository
    ) {
    }

    public function get_name(): string {
        return self::CAMPAIGN_NAME;
    }

    public function get_priority(): int {
        return 75;
    }

    protected function build_reach_out(): ?ReachOut {
        if ( ! $this->site_url_repository->is_temporary_domain() ) {
            $this->reset_state();

            return null;
        }

        return new ReachOut(
            $this->build_message(),
            self::DEDUP_KEY,
            $this->build_connect_domain_button( $this->url_builder->connect_domain_url() )
        );
    }

    private function build_message(): string {
        return __(
            'Hey! Your site is still running on a temporary domain. Connecting a custom domain makes your site look more professional and helps visitors find you. It only takes a few minutes!',
            'hostinger-ai-assistant'
        );
    }

    private function build_connect_domain_button( string $connect_url ): array {
        return array(
            array(
                'id'   => 'connect-domain',
                'type' => 'button',
                'data' => array(
                    'display_text' => __( 'Connect my domain', 'hostinger-ai-assistant' ),
                    'url'          => esc_url_raw( $connect_url ),
                ),
            ),
        );
    }
}
