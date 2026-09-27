<?php

namespace Hostinger\AiTheme\Compatibility;

defined( 'ABSPATH' ) || exit;

class LiteSpeedCache {
    private const CONFIGURED_OPTION = 'hostinger_ai_litespeed_configured';

    public function __construct() {
        if ( ! defined( 'LSCWP_V' ) ) {
            return;
        }

        add_action( 'init', array( $this, 'maybe_configure_object_cache' ) );
        add_action( 'init', array( $this, 'setup_woocommerce_compatibility' ) );
        add_action( 'woocommerce_ajax_get_refreshed_fragments', array( $this, 'set_nocache' ), 1 );
    }

    public function maybe_configure_object_cache(): void {
        if ( get_option( self::CONFIGURED_OPTION ) ) {
            return;
        }

        if ( ! class_exists( '\LiteSpeed\Conf' ) ) {
            return;
        }

        $non_persistent_groups = apply_filters( 'litespeed_conf', 'object-non_persistent_groups' );
        $non_persistent_groups = is_array( $non_persistent_groups ) ? $non_persistent_groups : array();

        foreach ( array( 'options', 'site-options' ) as $group ) {
            if ( ! in_array( $group, $non_persistent_groups, true ) ) {
                $non_persistent_groups[] = $group;
            }
        }

        ( new \LiteSpeed\Conf() )->update_confs(
            array(
                'object-admin'                 => 0,
                'cache-priv'                   => 0,
                'cache-rest'                   => 0,
                'object-non_persistent_groups' => $non_persistent_groups,
            )
        );

        update_option( self::CONFIGURED_OPTION, true );
    }

    public function setup_woocommerce_compatibility(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        add_action( 'woocommerce_ajax_added_to_cart', array( $this, 'set_nocache' ) );
        add_action( 'woocommerce_add_to_cart', array( $this, 'set_nocache' ) );
        add_action( 'woocommerce_cart_updated', array( $this, 'set_nocache' ) );
    }

    public function set_nocache(): void {
        if ( has_action( 'litespeed_control_set_nocache' ) ) {
            do_action( 'litespeed_control_set_nocache', 'WooCommerce cart action' );
        }
    }
}
