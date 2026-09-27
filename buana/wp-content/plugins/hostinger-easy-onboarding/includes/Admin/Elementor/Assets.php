<?php

namespace Hostinger\EasyOnboarding\Admin\Elementor;

use Hostinger\EasyOnboarding\Admin\Redirects;
use Hostinger\EasyOnboarding\Helper;

defined( 'ABSPATH' ) || exit;

class Assets {
    public const SCRIPT_HANDLE = 'hostinger_easy_onboarding_elementor_editor';

    private Helper $helper;

    public function __construct() {
        $this->helper = new Helper();

        if ( ! Helper::is_plugin_active( 'elementor' ) ) {
            return;
        }

        add_action( 'elementor/editor/v2/scripts/enqueue', array( $this, 'enqueue_editor_scripts' ) );
    }

    public function enqueue_editor_scripts(): void {
        if ( ! $this->is_cta_visible() ) {
            return;
        }

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/js/elementor-editor.min.js',
            array(
                'react',
                'elementor-v2-ui',
                'elementor-v2-editor-app-bar',
            ),
            HOSTINGER_EASY_ONBOARDING_VERSION,
            true
        );

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'hostinger_easy_onboarding_elementor',
            array(
                'onboarding_page_url' => admin_url( Redirects::ONBOARDING_ADMIN_URI ),
                'label'               => __( 'Finish Setup', 'hostinger-easy-onboarding' ),
            )
        );
    }

    private function is_cta_visible(): bool {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        return ! $this->helper->is_website_onboarding_completed();
    }
}
