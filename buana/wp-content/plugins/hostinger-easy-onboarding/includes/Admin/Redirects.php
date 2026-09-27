<?php

namespace Hostinger\EasyOnboarding\Admin;

use Hostinger\EasyOnboarding\Settings;
use Hostinger\WpHelper\Utils as Helper;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Constants;
use Exception;

defined( 'ABSPATH' ) || exit;

class Redirects {

    private string $platform;
    public const PLATFORM_HPANEL           = 'hpanel';
    public const PLATFORM_ONBOARDING_STEPS = 'onboarding-steps';
    public const BUILDER_TYPE              = 'prebuilt';
    public const HOMEPAGE_DISPLAY          = 'page';
    public const ONBOARDING_ADMIN_URI      = 'admin.php?page=hostinger-get-onboarding';

    public function __construct() {
        if ( ! Settings::get_setting( 'first_login_at' ) ) {
            Settings::update_setting( 'first_login_at', gmdate( 'Y-m-d H:i:s' ) );
        }

        if ( isset( $_GET['platform'] ) ) {
            $this->platform = sanitize_text_field( $_GET['platform'] );

            if ( $this->platform === self::PLATFORM_HPANEL ) {
                $this->login_redirect();
            } elseif ( $this->platform === self::PLATFORM_ONBOARDING_STEPS ) {
                $this->onboarding_steps_redirect();
            }
        }
    }

    private function onboarding_steps_redirect(): void {
        add_action(
            'init',
            function () {
                wp_safe_redirect( admin_url( self::ONBOARDING_ADMIN_URI ) );
                exit;
            }
        );
    }

    private function is_new_website(): bool {
        $first_admin = get_users(
            array(
                'role'    => 'administrator',
                'orderby' => 'ID',
                'order'   => 'ASC',
                'number'  => 1,
            )
        );

        if ( empty( $first_admin ) ) {
            return true;
        }

        $registered_time = strtotime( $first_admin[0]->user_registered );

        return ( time() - $registered_time ) <= DAY_IN_SECONDS;
    }

    private function login_redirect(): void {
        $is_prebuilt_website = get_option( 'hostinger_builder_type', '' ) === self::BUILDER_TYPE;
        $is_woocommerce_page = in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
        $homepage_id         = ( get_option( 'show_on_front' ) === self::HOMEPAGE_DISPLAY ) ? get_option( 'page_on_front' ) : null;
        $is_gutenberg_page   = $homepage_id ? has_blocks( get_post( $homepage_id )->post_content ) : false;

        add_action(
            'init',
            function () use ( $is_prebuilt_website, $is_woocommerce_page, $homepage_id, $is_gutenberg_page ) {
                if ( $is_prebuilt_website && ! $is_woocommerce_page && $homepage_id && $is_gutenberg_page ) {
                    $redirect_url = get_edit_post_link( $homepage_id, '' );
                } else {
                    $redirect_url = admin_url( 'admin.php?page=hostinger' );
                }

                // Onboarding redirect.
                $hostinger_onboarding_completed = get_option( 'hostinger_onboarding_completed', false );
                if ( $hostinger_onboarding_completed === false && $this->is_new_website() ) {
                    $redirect_url = admin_url( 'admin.php?page=hostinger&action=onboarding' );
                }

                wp_safe_redirect( $redirect_url );
                exit;
            }
        );
    }
}
