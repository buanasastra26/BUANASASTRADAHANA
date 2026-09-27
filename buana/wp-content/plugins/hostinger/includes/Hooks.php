<?php

namespace Hostinger;

use Hostinger\Admin\Menu;
use Hostinger\Admin\PluginSettings;
use Hostinger\Admin\Jobs\NotifyMcpJob;
use Hostinger\Mcp\EventHandlerFactory;
use Hostinger\WpHelper\Utils;

defined( 'ABSPATH' ) || exit;

class Hooks {
    public function __construct() {
        add_filter( 'xmlrpc_enabled', array( $this, 'check_xmlrpc_enabled' ) );
        add_filter( 'wp_is_application_passwords_available', array( $this, 'check_authentication_password_enabled' ) );
        add_filter( 'wp_die_handler', array( $this, 'maybe_customize_application_password_die_handler' ) );
        add_filter( 'wp_headers', array( $this, 'check_pingback' ) );
        add_action( 'init', array( $this, 'plugins_loaded' ) );
        add_action( 'update_option_woocommerce_coming_soon', array( $this, 'litespeed_flush_cache' ) );
        add_action( 'update_option_woocommerce_store_pages_only', array( $this, 'litespeed_flush_cache' ) );
        add_action( 'transition_post_status', array( $this, 'handle_transition_post_status' ), 10, 3 );
        add_action( 'updated_option', array( $this, 'handle_updated_option' ), 10, 3 );
    }

    public function handle_transition_post_status( string $new_status, string $old_status, \WP_Post $post ): void {
        if ( $new_status === 'publish' || $old_status === 'publish' ) {
            do_action(
                NotifyMcpJob::JOB_NAME,
                array(
                    'event'   => EventHandlerFactory::MCP_EVENT_PAGE_UPDATED,
                    'post_id' => $post->ID,
                )
            );
        }
    }

    public function handle_updated_option( string $option, mixed $old_value, mixed $value ): void {
        if ( $option === 'cron' || $this->is_transient( $option ) ) {
            return;
        }

        if ( $old_value !== $value ) {
            do_action( NotifyMcpJob::JOB_NAME, array( 'event' => EventHandlerFactory::MCP_EVENT_UPDATED ) );
        }

        if ( $option === HOSTINGER_PLUGIN_SETTINGS_OPTION && isset( $value['optin_mcp'] ) && isset( $old_value['optin_mcp'] ) ) {
            if ( $old_value['optin_mcp'] !== $value['optin_mcp'] ) {
                do_action(
                    NotifyMcpJob::JOB_NAME,
                    array(
                        'event' => EventHandlerFactory::MCP_EVENT_OPTIN_TOGGLED,
                        'value' => $value['optin_mcp'],
                    )
                );
            }
        }
    }

    /**
     * @return void
     */
    public function plugins_loaded() {
        $utils           = new Utils();
        $plugin_settings = new PluginSettings();
        $settings        = $plugin_settings->get_plugin_settings();

        if ( defined( 'WP_CLI' ) && \WP_CLI ) {
            return;
        }

        // Xmlrpc.
        if ( $settings->get_disable_xml_rpc() && $utils->isThisPage( 'xmlrpc.php' ) ) {
            exit( 'Disabled' );
        }

        // SSL redirect.
        if ( $settings->get_force_https() && ! is_ssl() ) {
            if ( isset( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
                $host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );

                if ( $settings->get_force_www() && strpos( $host, 'www.' ) === false ) {
                    $host = 'www.' . $host;
                }

                wp_safe_redirect( 'https://' . $host . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 301 );
                exit;
            }
        }

        // Force www.
        if ( $settings->get_force_www() ) {
            if ( isset( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
                $host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );

                if ( strpos( $host, 'www.' ) === false ) {
                    wp_safe_redirect( 'https://www.' . $host . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 301 );
                    exit;
                }
            }
        }
    }

    /**
     * @param mixed $headers
     *
     * @return mixed
     */
    public function check_pingback( $headers ) {
        $plugin_settings = new PluginSettings();
        $settings        = $plugin_settings->get_plugin_settings();

        if ( $settings->get_disable_xml_rpc() ) {
            unset( $headers['X-Pingback'] );
        }

        return $headers;
    }

    /**
     * @param bool $enabled
     *
     * @return bool
     */
    public function check_xmlrpc_enabled( bool $enabled ): bool {
        $plugin_settings = new PluginSettings();
        $settings        = $plugin_settings->get_plugin_settings();

        if ( $settings->get_disable_xml_rpc() ) {
            return false;
        }

        return $enabled;
    }

    /**
     * @return bool
     */
    public function check_authentication_password_enabled(): bool {
        $plugin_settings = new PluginSettings();
        $settings        = $plugin_settings->get_plugin_settings();

        if ( $settings->get_disable_authentication_password() ) {
            return false;
        }

        return true;
    }

    public function maybe_customize_application_password_die_handler( $handler ) {
        $plugin_settings = new PluginSettings();
        $settings        = $plugin_settings->get_plugin_settings();

        if ( ! $settings->get_disable_authentication_password() ) {
            return $handler;
        }

        return function ( $message, $title = '', $args = array() ) use ( $handler ) {
            if ( is_string( $message ) && $message === __( 'Application passwords are not available.' ) ) {
                $message = $this->get_application_password_disabled_message();
            }

            call_user_func( $handler, $message, $title, $args );
        };
    }

    private function get_application_password_disabled_message(): string {
        $settings_url = admin_url( 'admin.php?page=' . Menu::MENU_SLUG );

        return sprintf(
            /* translators: %s: URL to the Hostinger Tools settings page. */
            __( 'Application passwords have been disabled by Hostinger for security reasons. You can turn them back on from <a href="%s">Hostinger Tools → Security settings</a>.', 'hostinger' ),
            esc_url( $settings_url )
        );
    }

    public function litespeed_flush_cache(): void {
        if ( has_action( 'litespeed_purge_all' ) ) {
            do_action( 'litespeed_purge_all' );
        }
    }

    private function is_transient( $option ): bool {
        return str_contains( $option, '_transient' );
    }
}
