<?php

namespace Hostinger\AiTheme;

use Hostinger\AiTheme\Admin\Menu;
use Hostinger\AiTheme\Builder\Helper;
use Hostinger\AiTheme\Data\HostingerEcommerceHelper;
use Hostinger\AiTheme\Data\NewEngineExperiment;
use Hostinger\WpHelper\Utils;

defined( 'ABSPATH' ) || exit;

class Redirects {
    /**
     * @var string
     */
    private string $platform;
    /**
     * @var Utils
     */
    private Utils $utils;
    public const PLATFORM_CONTENT_CREATOR = 'ai-website-generation';

    /**
     * @param Utils $utils
     */
    public function __construct( Utils $utils ) {
        $this->utils = $utils;

        add_action( 'admin_menu', array( $this, 'switch_ai_builder_versions' ) );
        add_action( 'admin_init', array( $this, 'redirect_to_builder' ) );

        if ( ! isset( $_GET['platform'] ) ) {
            return;
        }

        $this->platform = sanitize_text_field( $_GET['platform'] );
        $this->login_redirect();
    }

    /**
     * @return void
     */
    public function redirect_to_builder(): void {
        if ( wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return;
        }

        if ( $this->utils->isThisPage( Menu::AI_BUILDER_MENU_SLUG )
            || $this->utils->isThisPage( Menu::AI_BUILDER_V2_MENU_SLUG ) ) {
            return;
        }

        if ( Helper::has_finished_builder_onboarding() ) {
            return;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=' . ( NewEngineExperiment::is_enabled() || HostingerEcommerceHelper::is_active() ? Menu::AI_BUILDER_V2_MENU_SLUG : Menu::AI_BUILDER_MENU_SLUG ) ) );
        exit;
    }

    public function switch_ai_builder_versions(): void {
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        $ai_engine_experiment_enabled = NewEngineExperiment::is_enabled() || HostingerEcommerceHelper::is_active();

        if ( ! $ai_engine_experiment_enabled && $page === Menu::AI_BUILDER_V2_MENU_SLUG ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . Menu::AI_BUILDER_MENU_SLUG ) );
            exit;
        }

        if ( $ai_engine_experiment_enabled && $page === Menu::AI_BUILDER_MENU_SLUG ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . Menu::AI_BUILDER_V2_MENU_SLUG ) );
            exit;
        }
    }


    /**
     * @return void
     */
    private function login_redirect(): void {
        if ( $this->platform === self::PLATFORM_CONTENT_CREATOR ) {
            add_action(
                'init',
                function () {
                    wp_safe_redirect( admin_url( 'admin.php?page=' . ( NewEngineExperiment::is_enabled() || HostingerEcommerceHelper::is_active() ? Menu::AI_BUILDER_V2_MENU_SLUG : Menu::AI_BUILDER_MENU_SLUG ) ) );
                    exit;
                }
            );
        }
    }
}
