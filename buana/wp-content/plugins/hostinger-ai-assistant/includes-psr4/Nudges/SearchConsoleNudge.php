<?php

namespace Hostinger\AiAssistant\Nudges;

use Hostinger\AiAssistant\Hosting\EasyOnboardingRepository;
use Hostinger\AiAssistant\Hosting\SearchConsoleRepository;
use Hostinger\AiAssistant\Hosting\SiteUrlRepository;
use Hostinger\AiAssistant\Nudges\Dto\ReachOut;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class SearchConsoleNudge extends AbstractNudge {
    private const CAMPAIGN_NAME  = 'wordpress-connect-search-console';
    private const DEDUP_KEY      = 'setup-pending';
    private const SITE_KIT_PAGE  = 'admin.php?page=googlesitekit-splash';
    private const CHECKLIST_PAGE = 'admin.php?page=hostinger-get-onboarding';
    private const VISUAL_TYPE    = 'search_console_setup';

    protected const MIN_RELEVANT_AGE_DAYS = 3;

    public function __construct(
        private EasyOnboardingRepository $onboarding_repository,
        private SearchConsoleRepository $search_console_repository,
        private SiteUrlRepository $site_url_repository
    ) {
    }

    public function get_name(): string {
        return self::CAMPAIGN_NAME;
    }

    public function get_priority(): int {
        return 30;
    }

    protected function build_reach_out(): ?ReachOut {
        if ( ! $this->onboarding_repository->is_easy_onboarding_active()
            || ! $this->site_url_repository->is_custom_domain()
        ) {
            return null;
        }

        if ( $this->is_connected() ) {
            $this->reset_state();

            return null;
        }

        return new ReachOut(
            $this->build_message(),
            self::DEDUP_KEY,
            $this->build_setup_visual()
        );
    }

    private function is_connected(): bool {
        return $this->onboarding_repository->is_search_console_step_complete()
            || $this->search_console_repository->is_connected();
    }

    private function build_message(): string {
        return __(
            'Hey! Your site isn\'t connected to Google Search Console yet. Connecting it helps you get found on Google and see how visitors reach your site. It only takes a few minutes!',
            'hostinger-ai-assistant'
        );
    }

    private function build_setup_visual(): array {
        return array(
            array(
                'id'   => 'connect-search-console',
                'type' => self::VISUAL_TYPE,
                'data' => array(
                    'display_text' => __( 'Connect Search Console', 'hostinger-ai-assistant' ),
                    'plugin_slug'  => SearchConsoleRepository::SITE_KIT_SLUG,
                    'url'          => esc_url_raw( admin_url( self::SITE_KIT_PAGE ) ),
                    'fallback_url' => esc_url_raw( admin_url( self::CHECKLIST_PAGE ) ),
                ),
            ),
        );
    }
}
