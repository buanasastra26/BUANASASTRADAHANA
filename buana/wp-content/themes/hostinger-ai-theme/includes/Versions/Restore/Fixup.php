<?php

namespace Hostinger\AiTheme\Versions\Restore;

use Hostinger\AiTheme\Builder\LogoAdapter;
use Hostinger\AiTheme\Builder\WebsiteBuilder;
use Hostinger\AiTheme\Data\WebsiteTypeHelper;
use WP_Theme_JSON_Resolver;

defined( 'ABSPATH' ) || exit;

class Fixup {
    public function run( object $version, string $outgoing_builder_type ): void {
        $this->reset_theme_json_cache();

        WebsiteBuilder::clear_elementor_cache();

        $this->refresh_cache_buster();
        $this->repaint_logo();

        WebsiteBuilder::reconcile_plugins(
            WebsiteTypeHelper::get_website_types(),
            $outgoing_builder_type,
            (string) $version->builder_type
        );

        $this->flush_caches();
    }

    private function reset_theme_json_cache(): void {
        if ( class_exists( WP_Theme_JSON_Resolver::class ) && method_exists( WP_Theme_JSON_Resolver::class, 'clean_cached_data' ) ) {
            WP_Theme_JSON_Resolver::clean_cached_data();
        }
    }

    private function refresh_cache_buster(): void {
        update_option( 'hostinger_ai_version', time() );
    }

    private function repaint_logo(): void {
        $colors = get_option( 'hostinger_ai_colors', array() );

        ( new LogoAdapter() )->adapt_to_palette( is_array( $colors ) ? $colors : array() );
    }

    private function flush_caches(): void {
        delete_option( 'rewrite_rules' );

        if ( has_action( 'litespeed_purge_all' ) ) {
            do_action( 'litespeed_purge_all' );
        }

        wp_cache_flush();
    }
}
