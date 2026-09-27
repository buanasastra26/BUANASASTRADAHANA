<?php

namespace Hostinger\AiTheme\Builder;

use Throwable;
use WC_Product_Simple;
use WP_Term;

defined( 'ABSPATH' ) || exit;

class EnvelopeProducts {

    private ProductCategoryManager $categories;

    public function __construct( ?ProductCategoryManager $categories = null ) {
        $this->categories = $categories ?? new ProductCategoryManager();
    }

    public function is_available(): bool {
        return class_exists( 'WooCommerce' )
            && class_exists( 'WC_Product_Simple' )
            && function_exists( 'wc_get_product' )
            && taxonomy_exists( 'product_cat' );
    }

    public function create( array $woocommerce, array $media_map ): array {
        $products = $woocommerce['products'] ?? array();

        if ( empty( $products ) || ! is_array( $products ) ) {
            return array();
        }

        $term_id_by_slug  = $this->ensure_category_terms( $woocommerce, $products );
        $created_ids      = array();
        $catalog_kind     = $this->get_catalog_kind( $woocommerce );
        $catalog_location = $this->get_catalog_location( $woocommerce );

        foreach ( $products as $product ) {
            if ( empty( $product['name'] ) ) {
                continue;
            }

            $product_id = $this->create_product(
                $product,
                $term_id_by_slug,
                $media_map,
                $catalog_kind,
                $catalog_location
            );

            if ( $product_id > 0 ) {
                $created_ids[] = $product_id;
            }
        }

        return $created_ids;
    }

    private function ensure_category_terms( array $woocommerce, array $products ): array {
        $categories = array();

        foreach ( $woocommerce['categories'] ?? array() as $category ) {
            if ( is_array( $category ) || is_string( $category ) ) {
                $categories[] = $category;
            }
        }

        foreach ( $products as $product ) {
            foreach ( $product['categories'] ?? array() as $category ) {
                if ( is_array( $category ) || is_string( $category ) ) {
                    $categories[] = $category;
                }
            }
        }

        if ( empty( $categories ) ) {
            return array();
        }

        $map = array();

        foreach ( $this->categories->ensure_category_terms( $categories ) as $term ) {
            $map[ $term->slug ] = (int) $term->term_id;
        }

        return $map;
    }

    private function create_product(
        array $product,
        array $term_id_by_slug,
        array $media_map,
        string $catalog_kind,
        string $catalog_location
    ): int {
        // CRUD save() writes the wc_product_meta_lookup row the shop loop reads.
        $wc_product = new WC_Product_Simple();

        $wc_product->set_name( sanitize_text_field( (string) $product['name'] ) );
        $wc_product->set_description( wp_kses_post( (string) ( $product['description'] ?? '' ) ) );
        $wc_product->set_short_description( wp_kses_post( (string) ( $product['short_description'] ?? '' ) ) );
        $wc_product->set_status( 'publish' );

        // Without the stamp, a product missed by the tracked-ids option stays in the shop forever.
        $wc_product->update_meta_data( '_hostinger_ai_generated', '1' );

        $product_catalog_kind = $this->get_catalog_kind( $product, $catalog_kind );
        $wc_product->update_meta_data( '_hostinger_ai_catalog_kind', $product_catalog_kind );

        if ( $product_catalog_kind === 'service' ) {
            $wc_product->set_virtual( true );
            $this->set_service_metadata( $wc_product, $product, $catalog_location );
        }

        if ( ! empty( $product['slug'] ) ) {
            $wc_product->set_slug( sanitize_title( (string) $product['slug'] ) );
        }

        $price = isset( $product['regular_price'] ) ? (string) $product['regular_price'] : '';
        if ( $price !== '' ) {
            $wc_product->set_regular_price( $price );
            $wc_product->set_price( $price );
        }

        if ( ! empty( $product['sku'] ) ) {
            try {
                $wc_product->set_sku( sanitize_text_field( (string) $product['sku'] ) );
            } catch ( Throwable ) {
                // A duplicate or invalid SKU must not cost us the product.
            }
        }

        try {
            $product_id = (int) $wc_product->save();
        } catch ( Throwable ) {
            return 0;
        }

        if ( $product_id < 1 ) {
            return 0;
        }

        $this->attach_featured_image( $product_id, $product, $media_map );
        $this->assign_categories( $product_id, $product, $term_id_by_slug );

        return $product_id;
    }

    private function attach_featured_image( int $product_id, array $product, array $media_map ): void {
        $images = $product['images'] ?? array();
        if ( empty( $images ) || ! is_array( $images ) ) {
            return;
        }

        $src = $images[0]['src'] ?? '';
        if ( is_string( $src ) && isset( $media_map[ $src ] ) ) {
            set_post_thumbnail( $product_id, (int) $media_map[ $src ]['id'] );
        }
    }

    private function assign_categories( int $product_id, array $product, array $term_id_by_slug ): void {
        $term_ids = array();

        foreach ( $product['categories'] ?? array() as $category ) {
            $slug = $this->get_category_slug( $category );

            if ( $slug !== '' && isset( $term_id_by_slug[ $slug ] ) ) {
                $term_ids[] = $term_id_by_slug[ $slug ];
            }
        }

        if ( ! empty( $term_ids ) ) {
            wp_set_object_terms( $product_id, array_values( array_unique( $term_ids ) ), 'product_cat', false );
        }
    }

    private function get_category_slug( $category ): string {
        if ( is_string( $category ) ) {
            return sanitize_title( $category );
        }

        if ( ! is_array( $category ) ) {
            return '';
        }

        $slug = $category['slug'] ?? ( $category['name'] ?? '' );

        return is_string( $slug ) ? sanitize_title( $slug ) : '';
    }

    private function get_catalog_kind( array $data, string $fallback = 'product' ): string {
        $catalog_kind = $data['catalog_kind'] ?? $fallback;
        $catalog_kind = is_string( $catalog_kind ) ? sanitize_key( $catalog_kind ) : $fallback;

        return in_array( $catalog_kind, array( 'product', 'service' ), true ) ? $catalog_kind : $fallback;
    }

    private function get_catalog_location( array $woocommerce ): string {
        $location = $woocommerce['booking_location'] ?? ( $woocommerce['location'] ?? '' );

        return is_string( $location ) ? sanitize_text_field( $location ) : '';
    }

    private function set_service_metadata(
        WC_Product_Simple $wc_product,
        array $product,
        string $catalog_location
    ): void {
        $duration = absint( $product['duration_minutes'] ?? 0 );

        if ( $duration > 0 ) {
            $wc_product->update_meta_data( '_hostinger_ai_duration_minutes', $duration );
        }

        $location = $product['booking_location'] ?? ( $product['location'] ?? $catalog_location );

        if ( is_string( $location ) && $location !== '' ) {
            $wc_product->update_meta_data( '_hostinger_ai_booking_location', sanitize_text_field( $location ) );
        }
    }
}
