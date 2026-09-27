<?php

namespace Hostinger\AiTheme\Builder\Elementor;

use Elementor\Core\Files\CSS\Global_CSS as ElementorGlobalCss;
use Elementor\Core\Files\CSS\Post as ElementorPostCss;
use Elementor\Plugin as ElementorPlugin;
use Hostinger\AiTheme\Builder\BundleSchema;
use Hostinger\AiTheme\Builder\Helper;
use Hostinger\AiTheme\Constants\BuilderType;
use Throwable;

defined( 'ABSPATH' ) || exit;

class CssCache {
    private const CLEARED_POST_META = array(
        '_elementor_css',
        '_elementor_element_cache',
        '_elementor_page_assets',
    );

    private const CSS_DIR_SUFFIX = '/elementor/css/';

    private const CLEARED_OPTIONS = array(
        '_elementor_global_css',
        '_elementor_assets_data',
        'elementor-custom-breakpoints-files',
    );

    public static function bust_all( string $reason ): void {
        if ( get_option( 'hostinger_ai_builder_type', '' ) === BuilderType::ELEMENTOR ) {
            self::clear();
        }

        if ( class_exists( '\LiteSpeed\Purge' ) ) {
            $purge = new \LiteSpeed\Purge();
            $purge::purge_all( $reason );
        }
    }

    public static function clear( array $page_ids = array() ): void {
        $page_ids = self::resolve_page_ids( $page_ids );

        if ( ! self::is_runtime_ready() ) {
            self::clear_stored_css();

            return;
        }

        $files_manager = ElementorPlugin::$instance->files_manager;

        if ( method_exists( $files_manager, 'clear_cache' ) ) {
            $files_manager->clear_cache();
        } else {
            self::clear_stored_css();
        }

        self::regenerate( $page_ids );
    }

    private static function regenerate( array $page_ids ): void {
        if ( ! class_exists( ElementorPostCss::class ) ) {
            return;
        }

        $kit_id = (int) get_option( 'elementor_active_kit' );

        if ( $kit_id > 0 && get_post_status( $kit_id ) !== false ) {
            self::update_post_css( $kit_id );
        }

        foreach ( $page_ids as $page_id ) {
            if ( get_post_status( $page_id ) !== false ) {
                self::update_post_css( $page_id );
            }
        }

        if ( class_exists( ElementorGlobalCss::class ) ) {
            self::guard( fn() => ElementorGlobalCss::create( 'global.css' )->update(), 'global.css' );
        }
    }

    private static function update_post_css( int $post_id ): void {
        self::guard( fn() => ElementorPostCss::create( $post_id )->update(), 'post ' . $post_id );
    }

    private static function guard( callable $update, string $subject ): void {
        try {
            $update();
        } catch ( Throwable $e ) {
            Helper::log( sprintf( 'Elementor CSS regeneration failed for %s: %s', $subject, $e->getMessage() ) );
        }
    }

    private static function clear_stored_css(): void {
        foreach ( self::CLEARED_POST_META as $meta_key ) {
            delete_post_meta_by_key( $meta_key );
        }

        foreach ( self::CLEARED_OPTIONS as $option_name ) {
            delete_option( $option_name );
        }

        self::delete_css_files();
    }

    private static function delete_css_files(): void {
        $css_dir = self::get_css_dir();

        if ( ! str_ends_with( $css_dir, self::CSS_DIR_SUFFIX ) || ! is_dir( $css_dir ) ) {
            return;
        }

        foreach ( (array) glob( $css_dir . '*' ) as $file_path ) {
            if ( is_file( $file_path ) ) {
                wp_delete_file( $file_path );
            }
        }
    }

    // Not Files\Base::get_base_uploads_dir(): since Elementor 4.3 that reads the
    // e_optimized_css_files experiment off an experiments manager this path lacks.
    private static function get_css_dir(): string {
        $basedir = (string) ( wp_upload_dir( null, false )['basedir'] ?? '' );

        return $basedir === '' ? '' : trailingslashit( $basedir ) . 'elementor/css/';
    }

    private static function resolve_page_ids( array $page_ids ): array {
        $envelope_pages = (array) get_option( BundleSchema::OPTION_PAGES, array() );
        $legacy_pages   = (array) get_option( 'hostinger_ai_created_pages', array() );

        foreach ( $legacy_pages as $legacy_page ) {
            if ( is_array( $legacy_page ) && ! empty( $legacy_page['page_id'] ) ) {
                $page_ids[] = $legacy_page['page_id'];
            }
        }

        $page_ids = array_map( 'absint', array_merge( $page_ids, $envelope_pages ) );

        return array_values( array_unique( array_filter( $page_ids ) ) );
    }

    private static function is_runtime_ready(): bool {
        if ( ! class_exists( ElementorPlugin::class ) || ! class_exists( ElementorPostCss::class ) ) {
            return false;
        }

        $elementor = ElementorPlugin::$instance;

        return $elementor !== null && ! empty( $elementor->files_manager );
    }
}
