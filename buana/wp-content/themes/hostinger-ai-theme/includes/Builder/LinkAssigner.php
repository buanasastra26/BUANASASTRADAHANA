<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class LinkAssigner {
    private string $section_type;
    private array $elements;
    private LinkPool $pool;

    /**
     * @var string[]
     */
    private array $used = array();

    /**
     * @var array<string,string>|null
     */
    private ?array $primary_by_index = null;

    public function __construct( string $section_type, array $elements, ?LinkPool $pool = null ) {
        $this->section_type = $section_type;
        $this->elements     = $elements;
        $this->pool         = $pool ?? new LinkPool();
    }

    /**
     * Return the next distinct link for a button at $index. Advances internal state.
     */
    public function take_for_index( int $index ): string {
        $primary = $this->get_primary_links()[ (string) $index ] ?? '';

        if ( $primary !== '' && ! in_array( $primary, $this->used, true ) ) {
            return $this->remember( $primary );
        }

        $link = $this->pool->take( $this->used );
        if ( $link !== null ) {
            return $this->remember( $link );
        }

        return $this->remember( home_url( '/' ) );
    }

    private function remember( string $link ): string {
        $this->used[] = $link;

        return $link;
    }

    /**
     * @return array<string,string>
     */
    private function get_primary_links(): array {
        if ( $this->primary_by_index === null ) {
            $this->primary_by_index = $this->resolve_primary_links();
        }

        return $this->primary_by_index;
    }

    /**
     * @return array<string,string>
     */
    private function resolve_primary_links(): array {
        if ( str_starts_with( $this->section_type, 'hero-for-online-store' )
            || str_starts_with( $this->section_type, 'product-list' ) ) {
            $first_index = $this->first_button_index();
            $shop_link   = $this->get_shop_link();

            if ( $first_index !== null && $shop_link !== '' ) {
                return array( $first_index => $shop_link );
            }

            return array();
        }

        if ( str_starts_with( $this->section_type, 'product-categories' ) ) {
            return $this->resolve_category_primary_links();
        }

        return array();
    }

    private function first_button_index(): ?string {
        foreach ( $this->elements as $element ) {
            if ( $this->is_button_element( $element ) ) {
                return (string) ( $element['index'] ?? '' );
            }
        }

        return null;
    }

    private function is_button_element( array $element ): bool {
        $class = $element['class'] ?? '';
        $type  = strtolower( (string) ( $element['type'] ?? '' ) );

        return $class === 'hostinger-ai-cta-button' || str_contains( $type, 'button' );
    }

    private function get_shop_link(): string {
        $shop_page_id = $this->get_valid_shop_page_id();

        if ( $shop_page_id > 0 ) {
            $permalink = get_permalink( $shop_page_id );
            if ( ! empty( $permalink ) ) {
                return $permalink;
            }
        }

        return $this->get_shop_link_from_slug();
    }

    private function get_valid_shop_page_id(): int {
        $shop_page_id = ( function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : get_option( 'woocommerce_shop_page_id' ) );
        if ( $shop_page_id > 0 ) {
            $shop_page = get_post( $shop_page_id );
            if ( $shop_page && $shop_page->post_type === 'page' && $shop_page->post_status === 'publish' ) {
                return $shop_page_id;
            }
        }

        $shop_page_slug = $this->get_shop_page_slug();
        $shop_page      = get_page_by_path( $shop_page_slug, OBJECT, 'page' );

        if ( ! $shop_page || $shop_page->post_status !== 'publish' ) {
            return 0;
        }

        return (int) $shop_page->ID;
    }

    private function get_shop_link_from_slug(): string {
        $shop_page_slug = $this->get_shop_page_slug();

        if ( empty( $shop_page_slug ) ) {
            return home_url( '/' );
        }

        return home_url( user_trailingslashit( $shop_page_slug ) );
    }

    private function get_shop_page_slug(): string {
        $shop_page_slug = sanitize_title( (string) get_option( 'hostinger_ai_woo_shop_page_key', 'shop' ) );

        return $shop_page_slug !== '' ? $shop_page_slug : 'shop';
    }

    /**
     * @return array<string,string>
     */
    private function resolve_category_primary_links(): array {
        $category_names = $this->extract_category_names_by_index();

        if ( empty( $category_names ) ) {
            return array();
        }

        $category_links = $this->category_links_by_name( array_values( $category_names ) );

        if ( empty( $category_links ) ) {
            return array();
        }

        $primary = array();
        foreach ( $category_names as $index => $name ) {
            if ( isset( $category_links[ $name ] ) ) {
                $primary[ (string) $index ] = $category_links[ $name ];
            }
        }

        return $primary;
    }

    /**
     * Seam so tests can stub category resolution without the product_cat taxonomy.
     *
     * @return array<string,string> name => link
     */
    protected function category_links_by_name( array $names ): array {
        return ( new ProductCategoryManager() )->ensure_category_links_by_name( $names );
    }

    /**
     * @return array<string,string> index => category name
     */
    private function extract_category_names_by_index(): array {
        $category_names = array();

        foreach ( $this->elements as $element ) {
            $class   = $element['class'] ?? '';
            $content = sanitize_text_field( $element['content'] ?? '' );
            $index   = (string) ( $element['index'] ?? '' );

            if ( $class !== 'hostinger-ai-title' || $content === '' || $index === '' ) {
                continue;
            }

            $category_names[ $index ] = $content;
        }

        return $category_names;
    }
}
