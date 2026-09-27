<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Constants\GenerationConstant;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the header and footer template parts off the front end until the AI
 * builder has generated the site.
 */
class TemplatePartVisibility {
    private const TEMPLATE_SLUGS = array( 'header', 'footer' );

    private const TEMPLATE_PART_BLOCK = 'core/template-part';

    public function init(): void {
        add_filter( 'pre_render_block', array( $this, 'maybe_suppress_part' ), 10, 2 );
    }

    public static function mark_ready(): void {
        update_option( GenerationConstant::TEMPLATE_PARTS_READY_OPTION, 1 );
    }

    public static function is_ready(): bool {
        if ( ! empty( get_option( GenerationConstant::TEMPLATE_PARTS_READY_OPTION, false ) ) ) {
            return true;
        }

        if ( ! self::has_generated_site() ) {
            return false;
        }

        self::mark_ready();

        return true;
    }

    public function maybe_suppress_part( mixed $pre_render, array $block ): mixed {
        if ( $pre_render !== null ) {
            return $pre_render;
        }

        if ( ! $this->is_gated_part( $block ) ) {
            return $pre_render;
        }

        if ( ! $this->is_frontend_render() ) {
            return $pre_render;
        }

        if ( self::is_ready() ) {
            return $pre_render;
        }

        return '';
    }

    private static function has_generated_site(): bool {
        if ( get_option( GenerationConstant::STATE_OPTION ) === GenerationConstant::STATE_COMPLETE ) {
            return true;
        }

        if ( ! empty( get_option( Helper::HOSTINGER_AI_THEME_GENERATED_ONCE_OPTION, false ) ) ) {
            return true;
        }

        if ( ! empty( get_option( 'hostinger_ai_created_pages', array() ) ) ) {
            return true;
        }

        return self::has_built_part();
    }

    private static function has_built_part(): bool {
        $built_parts = get_posts(
            array(
                'post_type'     => 'wp_template_part',
                'post_status'   => 'publish',
                'post_name__in' => self::TEMPLATE_SLUGS,
                'numberposts'   => 1,
                'fields'        => 'ids',
                'tax_query'     => array(
                    array(
                        'taxonomy' => 'wp_theme',
                        'field'    => 'name',
                        'terms'    => get_stylesheet(),
                    ),
                ),
            )
        );

        return ! empty( $built_parts );
    }

    private function is_gated_part( array $block ): bool {
        if ( ( $block['blockName'] ?? '' ) !== self::TEMPLATE_PART_BLOCK ) {
            return false;
        }

        $slug = $block['attrs']['slug'] ?? '';

        if ( ! is_string( $slug ) ) {
            return false;
        }

        foreach ( self::TEMPLATE_SLUGS as $gated_slug ) {
            if ( str_starts_with( $slug, $gated_slug ) ) {
                return true;
            }
        }

        return false;
    }

    private function is_frontend_render(): bool {
        if ( is_admin() ) {
            return false;
        }

        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return false;
        }

        return ! wp_is_json_request();
    }
}
