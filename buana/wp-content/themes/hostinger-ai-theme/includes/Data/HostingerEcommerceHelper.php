<?php

namespace Hostinger\AiTheme\Data;

defined( 'ABSPATH' ) || exit;

class HostingerEcommerceHelper {
    public const START_FLOW_OPTION = 'hostinger_ecommerce_flow';
    public const START_FLOW_VALUE   = 'ecomm-add-storefront';
    public const PROMPT_OPTION      = 'hostinger_ecommerce_ai_prompt';
    public const STORE_ID_OPTION    = 'hostinger_ecommerce_store_id';
    public const CHANNEL_ID_OPTION  = 'hostinger_ecommerce_channel_id';
    public const COUNTRY_OPTION     = 'hostinger_ecommerce_country';
    public const LANGUAGE_OPTION    = 'hostinger_ecommerce_language';
    public const CART_PAGE_ID_OPTION     = 'hostinger_ecommerce_cart_page_id';
    public const PRODUCTS_PAGE_ID_OPTION = 'hostinger_ecommerce_products_page_id';

    /*
     * Shortcodes rendered by the Hostinger Ecommerce plugin (mirrors PagesCreator).
     */
    public const CART_SHORTCODE     = 'hostinger_ecommerce_cart';
    public const PRODUCTS_SHORTCODE = 'hostinger_ecommerce_products';

    /*
     * This transient prevents the attach-channel request from repeating on each admin_init.
     */
    public const CONNECTING_TRANSIENT     = 'hostinger_ecommerce_connecting';
    public const CONNECTING_TRANSIENT_TTL = 5 * MINUTE_IN_SECONDS;

    public static function is_active(): bool {
        return get_option( self::START_FLOW_OPTION ) === self::START_FLOW_VALUE;
    }

    public static function get_prompt(): string {
        return sanitize_textarea_field( (string) get_option( self::PROMPT_OPTION, '' ) );
    }

    public static function maybe_update_prompt( string $prompt ): void {
        if ( ! self::is_active() ) {
            return;
        }

        update_option( self::PROMPT_OPTION, sanitize_textarea_field( $prompt ) );
    }

    public static function get_store_id(): string {
        return sanitize_text_field( (string) get_option( self::STORE_ID_OPTION, '' ) );
    }

    public static function get_channel_id(): string {
        return sanitize_text_field( (string) get_option( self::CHANNEL_ID_OPTION, '' ) );
    }

    public static function needs_store_connection(): bool {
        return self::is_active()
            && self::get_store_id() !== ''
            && self::get_channel_id() === ''
            && ! self::is_connecting();
    }

    public static function is_connecting(): bool {
        return (bool) get_transient( self::CONNECTING_TRANSIENT );
    }

    public static function set_is_connecting(): void {
        set_transient( self::CONNECTING_TRANSIENT, 1, self::CONNECTING_TRANSIENT_TTL );
    }

    public static function clear_is_connecting(): void {
        delete_transient( self::CONNECTING_TRANSIENT );
    }

    public static function get_country(): string {
        return sanitize_text_field( (string) get_option( self::COUNTRY_OPTION, '' ) );
    }

    public static function get_language(): string {
        return sanitize_text_field( (string) get_option( self::LANGUAGE_OPTION, '' ) );
    }

    public static function get_cart_url(): string {
        $cart_page_id = (int) get_option( self::CART_PAGE_ID_OPTION, 0 );

        if ( $cart_page_id > 0 ) {
            $permalink = get_permalink( $cart_page_id );
            if ( is_string( $permalink ) && $permalink !== '' ) {
                return $permalink;
            }
        }

        return home_url( '/cart' );
    }

    public static function maybe_apply_country(): void {
        if ( ! self::is_active() ) {
            return;
        }

        $country = self::get_country();
        if ( $country !== '' ) {
            update_option( 'hostinger_country', $country );
        }
    }
}
