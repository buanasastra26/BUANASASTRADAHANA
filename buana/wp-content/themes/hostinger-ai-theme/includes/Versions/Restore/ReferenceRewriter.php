<?php

namespace Hostinger\AiTheme\Versions\Restore;

defined( 'ABSPATH' ) || exit;

class ReferenceRewriter {
    public const SCALAR_OPTION_REFS = array(
        'page_on_front'                          => IdMap::KIND_POST,
        'woocommerce_shop_page_id'               => IdMap::KIND_POST,
        'woocommerce_cart_page_id'               => IdMap::KIND_POST,
        'woocommerce_checkout_page_id'           => IdMap::KIND_POST,
        'woocommerce_myaccount_page_id'          => IdMap::KIND_POST,
        'elementor_active_kit'                   => IdMap::KIND_POST,
        'hostinger_ai_envelope_menu_id'          => IdMap::KIND_POST,
        'hostinger_ai_envelope_original_logo_id' => IdMap::KIND_ATTACHMENT,
        'hostinger_ai_envelope_adapted_logo_id'  => IdMap::KIND_ATTACHMENT,
    );
    public const LIST_OPTION_REFS   = array(
        'hostinger_ai_created_pages'              => array(
            'kind' => IdMap::KIND_POST,
            'key'  => 'page_id',
        ),
        'hostinger_ai_created_blog_posts'         => array(
            'kind' => IdMap::KIND_POST,
            'key'  => null,
        ),
        'hostinger_ai_created_products'           => array(
            'kind' => IdMap::KIND_POST,
            'key'  => null,
        ),
        'hostinger_ai_created_product_categories' => array(
            'kind' => IdMap::KIND_TERM,
            'key'  => null,
        ),
        'hostinger_ai_envelope_pages'             => array(
            'kind' => IdMap::KIND_POST,
            'key'  => null,
        ),
        'hostinger_ai_created_blog_categories'    => array(
            'kind' => IdMap::KIND_TERM,
            'key'  => null,
        ),
    );
    public const THEME_MOD_REFS     = array(
        'custom_logo' => IdMap::KIND_ATTACHMENT,
    );
    public const POSTMETA_REFS      = array(
        '_thumbnail_id'                   => IdMap::KIND_ATTACHMENT,
        'hostinger_preview_image_id'      => IdMap::KIND_ATTACHMENT,
        'hostinger_preview_image_post_id' => IdMap::KIND_POST,
    );

    /**
     * wp_posts column => id kind.
     */
    public const POST_COLUMN_REFS = array(
        'post_parent' => IdMap::KIND_POST,
    );

    /**
     * Block name => [attribute, kind].
     */
    public const BLOCK_ATTRIBUTE_REFS = array(
        'core/navigation-link'    => array(
            'attr' => 'id',
            'kind' => IdMap::KIND_POST,
        ),
        'core/navigation-submenu' => array(
            'attr' => 'id',
            'kind' => IdMap::KIND_POST,
        ),
        'core/navigation'         => array(
            'attr' => 'ref',
            'kind' => IdMap::KIND_POST,
        ),
    );

    private IdMap $map;

    public function __construct( IdMap $map ) {
        $this->map = $map;
    }

    public function is_scalar_option_ref( string $option_name ): bool {
        return isset( self::SCALAR_OPTION_REFS[ $option_name ] );
    }

    public function is_list_option_ref( string $option_name ): bool {
        return isset( self::LIST_OPTION_REFS[ $option_name ] );
    }

    public function rewrite_option_value( string $option_name, $value ) {
        if ( $this->is_scalar_option_ref( $option_name ) ) {
            return $this->remap_id( self::SCALAR_OPTION_REFS[ $option_name ], $value );
        }

        if ( $this->is_list_option_ref( $option_name ) ) {
            return $this->rewrite_list_option( self::LIST_OPTION_REFS[ $option_name ], $value );
        }

        return $value;
    }

    private function rewrite_list_option( array $spec, $value ) {
        if ( ! is_array( $value ) ) {
            return $value;
        }

        $rewritten = array();

        foreach ( $value as $index => $entry ) {
            if ( $spec['key'] === null ) {
                $mapped = $this->remap_id_or_null( $spec['kind'], $entry );

                if ( $mapped === null ) {
                    continue;
                }

                $rewritten[ $index ] = $mapped;
                continue;
            }

            if ( ! is_array( $entry ) || ! isset( $entry[ $spec['key'] ] ) ) {
                $rewritten[ $index ] = $entry;
                continue;
            }

            $mapped = $this->remap_id_or_null( $spec['kind'], $entry[ $spec['key'] ] );

            // Drop ids this snapshot never captured: keeping them failed restore verify.
            if ( $mapped === null ) {
                continue;
            }

            $entry[ $spec['key'] ] = $mapped;
            $rewritten[ $index ]   = $entry;
        }

        return $rewritten;
    }

    /**
     * @param mixed $value
     */
    private function remap_id_or_null( string $kind, $value ): ?int {
        if ( ! is_numeric( $value ) ) {
            return null;
        }

        $snapshot_id = (int) $value;

        if ( $snapshot_id <= 0 || ! $this->map->has( $kind, $snapshot_id ) ) {
            return null;
        }

        return $this->map->get( $kind, $snapshot_id );
    }

    public function rewrite_theme_mod( string $mod_key, $value ): array {
        if ( ! isset( self::THEME_MOD_REFS[ $mod_key ] ) ) {
            return array(
                'value'   => $value,
                'dropped' => false,
            );
        }

        $snapshot_id = (int) $value;

        if ( $snapshot_id <= 0 ) {
            return array(
                'value'   => $value,
                'dropped' => false,
            );
        }

        $kind = self::THEME_MOD_REFS[ $mod_key ];

        if ( $this->map->has( $kind, $snapshot_id ) ) {
            return array(
                'value'   => $this->map->get( $kind, $snapshot_id ),
                'dropped' => false,
            );
        }

        if ( 'attachment' === get_post_type( $snapshot_id ) ) {
            return array(
                'value'   => $snapshot_id,
                'dropped' => false,
            );
        }

        return array(
            'value'   => null,
            'dropped' => true,
        );
    }

    public function is_postmeta_ref( string $meta_key ): bool {
        return isset( self::POSTMETA_REFS[ $meta_key ] );
    }

    public function rewrite_postmeta( string $meta_key, $value ) {
        if ( ! $this->is_postmeta_ref( $meta_key ) ) {
            return $value;
        }

        return $this->remap_id( self::POSTMETA_REFS[ $meta_key ], $value );
    }

    public function rewrite_post_column( string $column, int $value ): int {
        if ( ! isset( self::POST_COLUMN_REFS[ $column ] ) ) {
            return $value;
        }

        return (int) $this->remap_id( self::POST_COLUMN_REFS[ $column ], $value );
    }

    public function rewrite_content( string $content ): string {
        return $this->rewrite_slugs( $this->rewrite_block_attributes( $content ) );
    }

    public function rewrite_block_attributes( string $content ): string {
        if ( '' === trim( $content ) || ! str_contains( $content, '<!-- wp:' ) ) {
            return $content;
        }

        $blocks = parse_blocks( $content );

        foreach ( $blocks as &$block ) {
            $this->rewrite_block( $block );
        }

        unset( $block );

        return serialize_blocks( $blocks );
    }

    private function rewrite_block( array &$block ): void {
        $block_name = $block['blockName'] ?? '';

        if ( isset( self::BLOCK_ATTRIBUTE_REFS[ $block_name ] ) ) {
            $spec = self::BLOCK_ATTRIBUTE_REFS[ $block_name ];

            if ( isset( $block['attrs'][ $spec['attr'] ] ) ) {
                $block['attrs'][ $spec['attr'] ] = (int) $this->remap_id( $spec['kind'], $block['attrs'][ $spec['attr'] ] );
            }
        }

        if ( ! empty( $block['innerBlocks'] ) ) {
            foreach ( $block['innerBlocks'] as &$inner_block ) {
                $this->rewrite_block( $inner_block );
            }

            unset( $inner_block );
        }
    }

    public function rewrite_slugs( string $content ): string {
        $changed = $this->map->get_changed_slugs();

        if ( empty( $changed ) || $content === '' ) {
            return $content;
        }

        $home = untrailingslashit( home_url() );

        $search  = array();
        $replace = array();

        foreach ( $changed as $old_slug => $new_slug ) {
            $old_url = $home . '/' . $old_slug . '/';
            $new_url = $home . '/' . $new_slug . '/';

            $search[]  = $old_url;
            $replace[] = $new_url;

            $search[]  = str_replace( '/', '\/', $old_url );
            $replace[] = str_replace( '/', '\/', $new_url );
        }

        return str_replace( $search, $replace, $content );
    }

    private function remap_id( string $kind, $value ) {
        if ( ! is_numeric( $value ) ) {
            return $value;
        }

        $snapshot_id = (int) $value;

        if ( $snapshot_id <= 0 || ! $this->map->has( $kind, $snapshot_id ) ) {
            return $value;
        }

        return $this->map->get( $kind, $snapshot_id );
    }
}
