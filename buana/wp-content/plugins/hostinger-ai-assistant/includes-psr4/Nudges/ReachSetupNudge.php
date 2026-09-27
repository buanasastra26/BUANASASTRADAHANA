<?php

namespace Hostinger\AiAssistant\Nudges;

use Hostinger\AiAssistant\Hosting\ReachRepository;
use Hostinger\AiAssistant\Nudges\Dto\ReachOut;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ReachSetupNudge extends AbstractNudge {
    private const CAMPAIGN_NAME = 'wordpress-setup-reach';
    private const DEDUP_KEY     = 'setup-pending';
    private const REACH_PAGE    = 'admin.php?page=hostinger-reach';

    protected const MIN_RELEVANT_AGE_DAYS = 3;

    public function __construct(
        private ReachRepository $reach_repository
    ) {
    }

    public function get_name(): string {
        return self::CAMPAIGN_NAME;
    }

    public function get_priority(): int {
        return 70;
    }

    protected function build_reach_out(): ?ReachOut {
        if ( ! $this->reach_repository->is_reach_active() ) {
            return null;
        }

        if ( $this->reach_repository->is_setup_complete() ) {
            $this->reset_state();

            return null;
        }

        return new ReachOut(
            $this->build_message(),
            self::DEDUP_KEY,
            $this->build_setup_button( admin_url( self::REACH_PAGE ) )
        );
    }

    private function build_message(): string {
        return __(
            'Hey! It looks like your Reach setup isn\'t complete yet. Finishing it will let you start collecting contacts for your newsletter. It only takes a few minutes!',
            'hostinger-ai-assistant'
        );
    }

    private function build_setup_button( string $setup_url ): array {
        return array(
            array(
                'id'   => 'setup-reach',
                'type' => 'button',
                'data' => array(
                    'display_text' => __( 'Connect site to Reach', 'hostinger-ai-assistant' ),
                    'url'          => esc_url_raw( $setup_url ),
                ),
            ),
        );
    }
}
