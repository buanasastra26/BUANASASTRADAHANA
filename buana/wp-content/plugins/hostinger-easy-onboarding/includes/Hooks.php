<?php

namespace Hostinger\EasyOnboarding;

use Hostinger\EasyOnboarding\Admin\Actions as Admin_Actions;
use Hostinger\EasyOnboarding\Admin\Onboarding\Onboarding;
use Hostinger\EasyOnboarding\AmplitudeEvents\Amplitude;
use Hostinger\EasyOnboarding\AmplitudeEvents\Actions as AmplitudeActions;
use WP_Admin_Bar;
use WP_REST_Request;
use WP_Post;
use WP_User;

defined( 'ABSPATH' ) || exit;

class Hooks {

    public const HOMEPAGE_DISPLAY = 'page';

    private Onboarding $onboarding;
    private Helper $helper;

    private bool $event_incremented = false;

    public function __construct() {
        $this->onboarding = new Onboarding();
        $this->helper     = new Helper();

        add_action( 'init', array( $this, 'check_url_and_flush_rules' ) );
        add_action( 'template_redirect', array( $this, 'admin_preview_website' ) );

        add_filter( 'hostinger_once_per_day_events', array( $this, 'limit_triggered_amplitude_events' ) );
        add_filter( 'hostinger_limited_per_day_events', array( $this, 'limit_edit_amplitude_events' ) );

        add_action( 'activate_plugin', array( $this, 'prevent_flexible_shipping_redirect' ) );
        add_action( 'activated_plugin', array( $this, 'maybe_mark_payments_step_completed' ) );
        add_action( 'update_option_WPLANG', array( $this, 'changed_site_language' ), 10, 3 );
        add_action( 'password_reset', array( $this, 'admin_password_reset_action' ), 10, 2 );
        add_action( 'profile_update', array( $this, 'admin_password_reset_action_on_edit' ), 10, 2 );
        add_action( 'rest_after_insert_post', array( $this, 'save_gutenberg_post_content' ), 10, 3 );
        add_action( 'rest_after_insert_page', array( $this, 'save_gutenberg_post_content' ), 10, 3 );
        add_action( 'save_post', array( $this, 'save_post_content' ), 10, 3 );
        add_action( 'updated_option', array( $this, 'save_settings' ), 10, 1 );
        add_action( 'customize_save_after', array( $this, 'save_customizer_settings' ) );
        add_action( 'admin_bar_menu', array( $this, 'customize_admin_bar_logo' ), 100 );
        add_action( 'admin_bar_menu', array( $this, 'custom_admin_bar_edit_site_link' ), 9999 );
        add_action( 'admin_bar_menu', array( $this, 'custom_admin_bar_edit_home_page_link' ), 9999 );

        add_action( 'plugins_loaded', array( $this, 'disable_prebuild_website_redirect' ) );

        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'pre_select_virtual_checkbox' ) );
    }

    public function pre_select_virtual_checkbox(): void {
        global $post;

        if ( ! $this->helper->is_selling_digital_products() ) {
            return;
        }

        if ( $post->post_status !== 'auto-draft' ) {
            return;
        }

        ?>

            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#_virtual').prop('checked', true);
                    $('#_virtual').trigger('change');
                });
            </script>

        <?php
    }

    public function disable_prebuild_website_redirect(): void {
        add_filter( 'srfm_enable_redirect_activation', fn( $do_redirect ) => false, 10 );
    }

    public function admin_password_reset_action( WP_User $user, string $new_pass ): void {
        $amplitude_events = new Amplitude();

        if ( ! in_array( 'administrator', (array) $user->roles, true ) ) {
            return;
        }

        $amplitude_events->send_event( array( 'action' => AmplitudeActions::WP_PASSWORD_RESET ) );
    }

    public function admin_password_reset_action_on_edit( int $user_id, WP_User $old_user_data ): void {
        $amplitude_events = new Amplitude();
        $user             = get_userdata( $user_id );

        if ( user_can( $user, 'administrator' ) && isset( $_POST['pass1'] ) && ! empty( $_POST['pass1'] ) ) {
            $amplitude_events->send_event( array( 'action' => AmplitudeActions::WP_PASSWORD_RESET ) );
        }
    }

    public function changed_site_language( mixed $old_value, mixed $new_value, string $option_name ): void {
        $amplitude_events = new Amplitude();
        $current_lang     = get_locale();
        $new_value        = $new_value ? $new_value : 'en_US';

        $params = array(
            'action'       => AmplitudeActions::WP_CHANGED_LANG,
            'old_language' => $current_lang,
            'new_language' => $new_value,
        );

        $amplitude_events->send_event( $params );
    }

    public function save_gutenberg_post_content( WP_Post $post, WP_REST_Request $request, bool $creating ): void {
        if ( Helper::should_skip_event() || $this->event_incremented || $creating ) {
            return;
        }

        $params = $request->get_json_params() ?? array();
        if ( ! isset( $params['content'] ) && ! isset( $params['title'] ) ) {
            return;
        }

        $debounce_key = 'hostinger_edit_event_' . $post->ID;
        if ( get_transient( $debounce_key ) ) {
            return;
        }

        $amplitude_events = new Amplitude();
        if ( $amplitude_events->can_send_edit_amplitude_event() ) {
            set_transient( $debounce_key, 1, 10 );
            $amplitude_events->send_edit_amplitude_event();
            $this->event_incremented = true;
        }
    }

    public function save_post_content( int $post_ID, WP_Post $post, bool $update ): void {
        if ( Helper::should_skip_event() || $this->event_incremented ) {
            return;
        }

        if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'editpost' ) {
            return;
        }

        $amplitude_events = new Amplitude();

        if ( $amplitude_events->can_send_edit_amplitude_event() ) {
            $amplitude_events->send_edit_amplitude_event();
            $this->event_incremented = true;
        }
    }

    public function save_settings( string $option_name ): void {
        $option_page_not_set = ! isset( $_POST['option_page'] );

        if ( Helper::should_skip_event() || $this->event_incremented || $option_page_not_set ) {
            return;
        }

        $amplitude_events = new Amplitude();

        if ( $amplitude_events->can_send_edit_amplitude_event() ) {
            $this->event_incremented = true;
            $amplitude_events->send_edit_amplitude_event();
        }
    }

    public function save_customizer_settings(): void {
        if ( Helper::should_skip_event() || $this->event_incremented ) {
            return;
        }

        $amplitude_events = new Amplitude();

        if ( $amplitude_events->can_send_edit_amplitude_event() ) {
            $amplitude_events->send_edit_amplitude_event();
            $this->event_incremented = true;
        }
    }

    public function check_url_and_flush_rules() {
        if ( defined( 'DOING_AJAX' ) && \DOING_AJAX ) {
            return false;
        }

        $current_url    = home_url( add_query_arg( null, null ) );
        $url_components = wp_parse_url( $current_url );

        if ( isset( $url_components['query'] ) ) {
            parse_str( $url_components['query'], $params );

            if ( isset( $params['app_name'] ) ) {
                $app_name = sanitize_text_field( $params['app_name'] );

                if ( $app_name === 'Omnisend App' ) {
                    if ( function_exists( 'flush_rewrite_rules' ) ) {
                        flush_rewrite_rules();
                    }

                    if ( has_action( 'litespeed_purge_all' ) ) {
                        do_action( 'litespeed_purge_all' );
                    }
                }
            }
        }
    }

    public function admin_preview_website() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        $amplitude = new Amplitude();

        $appearance      = get_option( 'hostinger_appearance', 'none' );
        $subscription_id = get_option( 'hostinger_subscription_id', 0 );

        $params = array(
            'action'          => 'wordpress.preview_site',
            'appearance'      => $appearance,
            'subscription_id' => $subscription_id,
        );

        $amplitude->send_event( $params );
    }

    public function limit_triggered_amplitude_events( $events ): array {
        $new_events = array(
            AmplitudeActions::WP_PREVIEW_SITE,
            AmplitudeActions::WP_EASY_ONBOARDING_ENTER,
            AmplitudeActions::WP_CONNECT_DOMAIN_SHOWN,
            AmplitudeActions::WP_CONNECT_DOMAIN_ENTER,
            AmplitudeActions::WP_EASY_ONBOARDING_COMPLETED,
            AmplitudeActions::WP_BLACK_FRIDAY_BANNER_OFFER_SHOWN,
            AmplitudeActions::WP_CHANGED_LANG,
            AmplitudeActions::WP_PASSWORD_RESET,
            AmplitudeActions::WP_REACH_BANNER_SHOWN,
        );

        return array_merge( $events, $new_events );
    }

    public function limit_edit_amplitude_events( array $events ): array {
        $events[ AmplitudeActions::WP_EDIT ] = 3;

        return $events;
    }

    // Mark payments step completed if Amazon Pay payment gateway plugin is activated because this payment gateway is enabled after activation right away.
    public function maybe_mark_payments_step_completed( string $plugin ): void {
        if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
            return;
        }

        if ( $plugin
            !== 'woocommerce-gateway-amazon-payments-advanced/woocommerce-gateway-amazon-payments-advanced.php' ) {
            return;
        }

        $this->onboarding->init();

        if ( $this->onboarding->is_completed(
            Onboarding::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID,
            Admin_Actions::ADD_PAYMENT
        ) ) {
            return;
        }

        $this->onboarding->complete_step(
            Onboarding::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID,
            Admin_Actions::ADD_PAYMENT
        );
    }

    public function prevent_flexible_shipping_redirect( string $plugin ): void {
        // Disable Flexible shipping activation redirect by setting value to true.
        if ( $plugin === 'flexible-shipping/flexible-shipping.php' ) {
            $flexible_shipping_redirect = get_option( 'flexible-shipping-activation-redirected', false );

            if ( empty( $flexible_shipping_redirect ) ) {
                update_option( 'flexible-shipping-activation-redirected', 1 );
            }
        }
    }

    public function customize_admin_bar_logo( WP_Admin_Bar $wp_admin_bar ): void {
        $wp_admin_bar->add_node(
            array(
                'id'   => 'wp-logo',
                'href' => admin_url(),
            )
        );
    }

    public function custom_admin_bar_edit_site_link( WP_Admin_Bar $wp_admin_bar ): void {
        if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
            return;
        }

        $wp_admin_bar->add_node(
            array(
                'id'    => 'site-editor',
                'title' => __( 'Edit site', 'hostinger-easy-onboarding' ),
                'href'  => admin_url( 'site-editor.php' ),
            )
        );
    }

    public function custom_admin_bar_edit_home_page_link( WP_Admin_Bar $wp_admin_bar ): void {
        $front_page_id   = get_option( 'page_on_front' );
        $show_on_front   = get_option( 'show_on_front' );
        $has_static_page = $show_on_front === self::HOMEPAGE_DISPLAY && $front_page_id;

        if ( ! $has_static_page ) {
            return;
        }

        if ( ! is_admin() && is_front_page() ) {
            return;
        }

        if ( $this->helper->is_page_built_with_elementor( (int) $front_page_id ) ) {
            $edit_url = $this->helper->get_elementor_edit_url( (int) $front_page_id );
        } else {
            $query_args = array(
                'post'   => $front_page_id,
                'action' => 'edit',
            );

            $edit_url = add_query_arg( $query_args, admin_url( 'post.php' ) );
        }

        $wp_admin_bar->add_node(
            array(
                'id'    => 'edit_home_page',
                'title' => __( 'Edit Home Page', 'hostinger-easy-onboarding' ),
                'href'  => $edit_url,
            )
        );
    }
}
