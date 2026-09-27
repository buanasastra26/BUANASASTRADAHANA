<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

use Hostinger\AiTheme\Builder\ImageManager;
use Hostinger\AiTheme\Constants\GenerationConstant;

class PageBuilder {
    /**
     * @var string
     */
    private array $content_data;
    private string $type;

    /**
     * @param array $content_data
     */
    public function __construct( array $content_data ) {
        $this->content_data = $content_data;
        $this->type         = get_option( 'hostinger_ai_builder_type', 'gutenberg' );
    }

    /**
     * @return array
     */
    public function build_pages(): array {
        $pages          = array();
        $pages_to_build = $this->content_data['pages'];

        unset( $pages_to_build['ecommercePagesGroup'] );

        $stubs = array();
        foreach ( $pages_to_build as $page => $page_data ) {
            $stub = $this->create_page_stub( $page );
            if ( empty( $stub ) ) {
                continue;
            }

            $stubs[ $page ] = array(
                'page_id'   => $stub['page_id'],
                'title'     => $stub['title'],
                'page_data' => $page_data,
            );
        }

        foreach ( $this->order_shop_first( array_keys( $stubs ) ) as $page ) {
            ImageManager::set_current_page_key( sanitize_title( $page ) );
            $this->populate_page( $page, $stubs[ $page ]['page_id'], $stubs[ $page ]['page_data'] );
        }

        foreach ( $stubs as $page => $stub ) {
            $pages[ $page ] = array(
                'title'   => $stub['title'],
                'page_id' => $stub['page_id'],
            );
        }

        update_option( 'hostinger_ai_created_pages', $pages );

        return $pages;
    }

    private function order_shop_first( array $page_keys ): array {
        if ( ! $this->is_store_flow() ) {
            return $page_keys;
        }

        usort(
            $page_keys,
            static function ( $a, $b ): int {
                $a_rank = strtolower( trim( (string) $a ) ) === 'shop' ? 0 : 1;
                $b_rank = strtolower( trim( (string) $b ) ) === 'shop' ? 0 : 1;

                return $a_rank <=> $b_rank;
            }
        );

        return $page_keys;
    }

    private function create_page_stub( string $page ): array {
        return match ( $this->type ) {
            'elementor' => $this->create_elementor_stub( $page ),
            default => $this->create_gutenberg_stub( $page ),
        };
    }

    private function populate_page( string $page, int $page_id, array $page_data ): void {
        if ( $this->type === 'elementor' ) {
            $this->populate_elementor_page( $page, $page_id, $page_data );
            return;
        }

        $this->populate_gutenberg_page( $page, $page_id, $page_data );
    }

    private function create_gutenberg_stub( string $page ): array {
        $page_clean = trim( $page );
        $page_title = mb_convert_case( $page_clean, MB_CASE_TITLE, 'UTF-8' );

        $new_page = array(
            'post_title'   => $page_title,
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'meta_input'   => array(
                GenerationConstant::META_KEY => '1',
            ),
        );

        if ( $this->is_shop_page( $page_clean ) ) {
            $new_page['post_name'] = $this->get_shop_page_slug( $page_clean );
        }

        $page_id = wp_insert_post( $new_page );

        if ( empty( $page_id ) ) {
            return array();
        }

        update_post_meta( $page_id, '_wp_page_template', 'no-title' );

        $this->register_shop_page( $page_id, $page_clean );

        return array(
            'page_id' => (int) $page_id,
            'title'   => $page_title,
        );
    }

    private function populate_gutenberg_page( string $page, int $page_id, array $page_data ): void {
        $page_clean   = trim( $page );
        $page_content = '';
        $seo_section  = array();

        if ( strtolower( $page_clean ) === 'shop' && get_option( 'hostinger_ai_woo', false ) ) {
            $page_content = '[products limit="12" columns="4" paginate="true"]';
        } elseif ( ! empty( $page_data['sections'] ) ) {
            foreach ( $page_data['sections'] as $section ) {
                if ( $section['type'] === 'seo' ) {
                    $seo_section = $section;
                    continue;
                }
                $content_parser = new ContentParser( $section );
                $page_content  .= $content_parser->output();
            }
        }

        wp_update_post(
            array(
                'ID'           => $page_id,
                'post_content' => $page_content,
            )
        );

        $seo = new Seo();
        $seo->load_seo_from_sections( $page_id, $seo_section );
        $seo->add_seo_meta_tags( $page_id );
    }

    private function create_elementor_stub( string $page ): array {
        $page_clean = trim( $page );
        $page_title = mb_convert_case( $page_clean, MB_CASE_TITLE, 'UTF-8' );

        $new_page = array(
            'post_title'   => $page_title,
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'meta_input'   => array(
                GenerationConstant::META_KEY => '1',
            ),
        );

        if ( $this->is_shop_page( $page_clean ) ) {
            $new_page['post_name'] = $this->get_shop_page_slug( $page_clean );
        }

        $page_id = wp_insert_post( $new_page );

        if ( empty( $page_id ) ) {
            return array();
        }

        $elementor_version = Helper::get_elementor_version();

        update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
        update_post_meta( $page_id, '_elementor_version', $elementor_version );
        update_post_meta( $page_id, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );

        $this->register_shop_page( $page_id, $page_clean );

        return array(
            'page_id' => (int) $page_id,
            'title'   => $page_title,
        );
    }

    private function populate_elementor_page( string $page, int $page_id, array $page_data ): void {
        $page_clean     = trim( $page );
        $elementor_data = array();
        $elementor_json = '';
        $seo_section    = array();

        if ( strtolower( $page_clean ) === 'shop' && get_option( 'hostinger_ai_woo', false ) ) {
            $elementor_json = $this->load_elementor_shop_template( 'shop.json' );
        }

        if ( empty( $elementor_json ) && ! empty( $page_data['sections'] ) ) {
            foreach ( $page_data['sections'] as $section ) {
                if ( $section['type'] === 'seo' ) {
                    $seo_section = $section;
                    continue;
                }
                $content_parser = new ContentParser( $section );
                $output         = $content_parser->output();
                if ( is_array( $output ) ) {
                    $elementor_data = array_merge( $elementor_data, $output );
                }
            }
            $elementor_json = json_encode( $elementor_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        }

        Helper::save_elementor_data( $page_id, $elementor_json );

        $seo = new Seo();
        $seo->load_seo_from_sections( $page_id, $seo_section );
        $seo->add_seo_meta_tags( $page_id );
    }

    private function register_shop_page( int $page_id, string $page_clean ): void {
        if ( ! $this->is_shop_page( $page_clean ) || ! get_post_status( $page_id ) ) {
            return;
        }

        if ( get_option( 'hostinger_ai_woo', false ) ) {
            update_option( 'woocommerce_shop_page_id', $page_id );
        }

        $post_name = get_post_field( 'post_name', $page_id );
        if ( ! empty( $post_name ) ) {
            update_option( 'hostinger_ai_woo_shop_page_key', $post_name );
        }
    }

    private function is_shop_page( string $page_clean ): bool {
        return strtolower( $page_clean ) === 'shop' && $this->is_store_flow();
    }

    private function is_store_flow(): bool {
        return (bool) get_option( 'hostinger_ai_woo', false );
    }

    private function load_elementor_shop_template( string $filename ): string {
        $path = get_stylesheet_directory() . '/blocks/elementor/' . $filename;
        if ( ! file_exists( $path ) ) {
            return '';
        }

        $template = file_get_contents( $path );

        return $template !== false ? $template : '';
    }

    private function get_shop_page_slug( string $page_clean ): string {
        $shop_page_slug = sanitize_title( (string) get_option( 'hostinger_ai_woo_shop_page_key', $page_clean ) );

        return $shop_page_slug !== '' ? $shop_page_slug : 'shop';
    }
}
