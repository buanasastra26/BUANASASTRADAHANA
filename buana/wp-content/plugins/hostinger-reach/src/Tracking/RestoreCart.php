<?php

namespace Hostinger\Reach\Tracking;

use Exception;
use Hostinger\Reach\Repositories\CartRepository;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class RestoreCart {

    public const QUERY_VAR   = 'hostinger_reach_restore_cart';
    public const TOKEN_VAR   = 'hostinger_reach_token';
    private const TOKEN_SALT = 'hostinger_reach_restore_cart|';

    private CartRepository $cart_repository;

    public function __construct( CartRepository $cart_repository ) {
        $this->cart_repository = $cart_repository;
    }

    public function init(): void {
        add_action( 'template_redirect', array( $this, 'maybe_restore_cart' ) );
    }

    /**
     * Build the signed URL that restores the given cart and forwards to checkout.
     */
    public static function get_restore_url( string $hash ): string {
        return add_query_arg(
            array(
                self::QUERY_VAR => rawurlencode( $hash ),
                self::TOKEN_VAR => self::generate_token( $hash ),
            ),
            home_url( '/' )
        );
    }

    public static function generate_token( string $hash ): string {
        return hash_hmac( 'sha256', self::TOKEN_SALT . $hash, wp_salt() );
    }

    public function maybe_restore_cart(): void {
        if ( ! isset( $_GET[ self::QUERY_VAR ], $_GET[ self::TOKEN_VAR ] ) ) {
            return;
        }

        $hash  = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) );
        $token = sanitize_text_field( wp_unslash( $_GET[ self::TOKEN_VAR ] ) );

        if ( ! $hash || ! $this->is_valid_token( $hash, $token ) ) {
            return;
        }

        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return;
        }

        try {
            $cart = $this->cart_repository->get( $hash );
        } catch ( Exception $e ) {
            return;
        }

        $this->restore_items( $cart['items'] ?? array() );

        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    private function is_valid_token( string $hash, string $token ): bool {
        return hash_equals( self::generate_token( $hash ), $token );
    }

    private function restore_items( array $items ): void {
        $cart = WC()->cart;
        $cart->empty_cart();

        foreach ( $items as $item ) {
            $product_id = (int) ( $item['product_id'] ?? 0 );
            $quantity   = (int) ( $item['quantity'] ?? 0 );

            if ( $product_id <= 0 || $quantity <= 0 ) {
                continue;
            }

            $product = wc_get_product( $product_id );
            if ( ! $product instanceof WC_Product ) {
                continue;
            }

            if ( $product->is_type( 'variation' ) ) {
                $cart->add_to_cart( $product->get_parent_id(), $quantity, $product_id, $product->get_variation_attributes() );
            } else {
                $cart->add_to_cart( $product_id, $quantity );
            }
        }
    }
}
