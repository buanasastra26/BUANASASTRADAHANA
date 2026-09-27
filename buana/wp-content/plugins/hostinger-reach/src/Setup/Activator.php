<?php

namespace Hostinger\Reach\Setup;

use Hostinger\Reach\Boot;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class Activator {
    public function __construct( string $plugin_file_name ) {
        register_activation_hook( $plugin_file_name, array( $this, 'activate_plugin' ) );
        add_action( 'plugins_loaded', array( $this, 'boot' ) );
        add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
    }

    public function activate_plugin(): void {
        if ( has_action( 'litespeed_purge_all' ) ) {
            do_action( 'litespeed_purge_all' );
        }

        add_option( 'hostinger_reach_activation_redirect', true );
    }

    public function maybe_redirect(): void {
        if ( ! get_option( 'hostinger_reach_activation_redirect' ) ) {
            return;
        }

        delete_option( 'hostinger_reach_activation_redirect' );

        $referer = wp_get_referer();
        if ( ! $referer || strpos( $referer, 'plugin-install.php' ) === false ) {
            return;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=hostinger-reach' ) );
        exit;
    }

    public function boot(): void {
        $boot = Boot::get_instance();
        $boot->plugins_loaded();
    }
}
