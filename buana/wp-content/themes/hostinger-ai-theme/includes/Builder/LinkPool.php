<?php

namespace Hostinger\AiTheme\Builder;

use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

class LinkPool {
    /**
     * Ordered, de-duplicated list of published permalinks. Null until loaded.
     *
     * @var string[]|null
     */
    private ?array $links = null;

    /**
     * @var string[]
     */
    private array $post_types;

    /**
     * @param string[] $post_types
     */
    public function __construct( array $post_types = array( 'post', 'page', 'product' ) ) {
        $this->post_types = $post_types;
    }

    /**
     * Return the next published permalink not present in $used, or null when the
     * distinct pool is exhausted.
     *
     * @param string[] $used
     */
    public function take( array $used = array() ): ?string {
        foreach ( $this->get_links() as $link ) {
            if ( ! in_array( $link, $used, true ) ) {
                return $link;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function get_links(): array {
        if ( $this->links !== null ) {
            return $this->links;
        }

        $args = array(
            'post_type'      => $this->post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        );

        $excluded_ids = $this->get_excluded_ids();
        if ( ! empty( $excluded_ids ) ) {
            $args['post__not_in'] = $excluded_ids;
        }

        $query = new WP_Query( $args );
        $links = array();

        foreach ( $query->posts as $post_id ) {
            $permalink = get_permalink( (int) $post_id );
            if ( ! empty( $permalink ) ) {
                $links[] = $permalink;
            }
        }

        $this->links = array_values( array_unique( $links ) );

        return $this->links;
    }

    /**
     * Post IDs that must never be used as a CTA destination (default WordPress
     * post and the WooCommerce cart page).
     *
     * @return int[]
     */
    private function get_excluded_ids(): array {
        $excluded = array();

        $hello_world = get_page_by_path( 'hello-world', OBJECT, 'post' );
        if ( $hello_world instanceof WP_Post ) {
            $excluded[] = (int) $hello_world->ID;
        }

        $cart_page_id = function_exists( 'wc_get_page_id' )
            ? (int) wc_get_page_id( 'cart' )
            : (int) get_option( 'woocommerce_cart_page_id' );

        if ( $cart_page_id > 0 ) {
            $excluded[] = $cart_page_id;
        }

        return array_values( array_unique( array_filter( $excluded, static fn( int $id ): bool => $id > 0 ) ) );
    }
}
