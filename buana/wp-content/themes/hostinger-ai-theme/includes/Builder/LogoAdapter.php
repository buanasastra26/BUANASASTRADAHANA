<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Points the site logo at a copy of the generated logo repainted for the current palette.
 *
 * At most one repainted copy exists at a time: the one the site is using. A restored
 * version may point at a copy that has since been replaced; the logo is then rebuilt
 * from the original for the restored palette.
 */
class LogoAdapter {
    use ColorUtils;

    public const VARIANT_META_KEY = '_hostinger_ai_logo_variant';

    private const MIN_INK_CONTRAST    = 4.5;
    private const MIN_ACCENT_CONTRAST = 3.0;

    private LogoRecolor $recolor;

    public function __construct( ?LogoRecolor $recolor = null ) {
        $this->recolor = $recolor ?? new LogoRecolor();
    }

    public function adapt_to_palette( array $colors ): ?string {
        $original_id = $this->get_original_logo_id();

        if ( $original_id === 0 || ! $this->generated_logo_in_use( $original_id ) ) {
            return null;
        }

        $targets = $this->targets_for( $colors );

        if ( $targets === null ) {
            return null;
        }

        if ( $targets === $this->targets_for( $this->get_original_colors() ) ) {
            self::discard_variants();

            return $this->use_logo( $original_id );
        }

        $key        = $this->variant_key( $original_id, $targets );
        $variant_id = $this->find_variant( $key );

        if ( $variant_id === 0 ) {
            $variant_id = $this->create_variant( $original_id, $targets, $key );
        }

        if ( $variant_id === 0 ) {
            return null;
        }

        self::delete_variants( $variant_id );
        update_option( BundleSchema::OPTION_ADAPTED_LOGO, $variant_id, false );

        return $this->use_logo( $variant_id );
    }

    public static function discard_variants(): void {
        delete_option( BundleSchema::OPTION_ADAPTED_LOGO );
        self::delete_variants();
    }

    public static function delete_variants( int $keep_id = 0 ): void {
        $ids = get_posts(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_key'       => self::VARIANT_META_KEY,
            )
        );

        foreach ( $ids as $id ) {
            if ( (int) $id !== $keep_id ) {
                wp_delete_attachment( (int) $id, true );
            }
        }
    }

    private function get_original_logo_id(): int {
        $original_id = (int) get_option( BundleSchema::OPTION_ORIGINAL_LOGO, 0 );

        return $original_id > 0 && get_post_type( $original_id ) === 'attachment' ? $original_id : 0;
    }

    private function generated_logo_in_use( int $original_id ): bool {
        $current = (int) get_theme_mod( 'custom_logo', 0 );
        $adapted = (int) get_option( BundleSchema::OPTION_ADAPTED_LOGO, 0 );

        if ( $current <= 0 ) {
            return $adapted > 0 && get_post_type( $adapted ) !== 'attachment';
        }

        return $current === $original_id || $current === $adapted;
    }

    private function get_original_colors(): array {
        $colors = get_option( BundleSchema::OPTION_ORIGINAL_COLORS, array() );

        return is_array( $colors ) ? $colors : array();
    }

    private function use_logo( int $attachment_id ): ?string {
        set_theme_mod( 'custom_logo', $attachment_id );

        $url = wp_get_attachment_image_url( $attachment_id, 'full' );

        return is_string( $url ) && $url !== '' ? $url : null;
    }

    private function targets_for( array $colors ): ?array {
        $background = $this->pick( $colors, 'page_background' );

        if ( $background === '' ) {
            $background = $this->pick( $colors, 'color1' );
        }

        if ( $background === '' ) {
            return null;
        }

        $ink = $this->pick( $colors, 'page_text' );

        if ( $ink === '' || $this->calculate_contrast_ratio( $ink, $background ) < self::MIN_INK_CONTRAST ) {
            $ink = $this->get_luminance( $background ) > 0.5 ? '#000000' : '#ffffff';
        }

        $accent = $this->pick( $colors, 'color3' );

        if ( $accent === '' ) {
            $accent = $ink;
        } elseif ( $this->calculate_contrast_ratio( $accent, $background ) < self::MIN_ACCENT_CONTRAST ) {
            $accent = $this->adjust_color_for_contrast( $accent, $background, self::MIN_ACCENT_CONTRAST );
        }

        return array(
            'ink'        => strtolower( $ink ),
            'accent'     => strtolower( $accent ),
            'background' => $background,
        );
    }

    private function pick( array $colors, string $key ): string {
        $camel = lcfirst( str_replace( '_', '', ucwords( $key, '_' ) ) );
        $value = $colors[ $key ] ?? ( $colors[ $camel ] ?? '' );

        if ( ! is_string( $value ) ) {
            return '';
        }

        $value = trim( $value );

        if ( ! $this->is_valid_hex_color( $value ) ) {
            return '';
        }

        $hex = ltrim( $value, '#' );

        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return '#' . strtolower( $hex );
    }

    private function variant_key( int $original_id, array $targets ): string {
        return md5( $original_id . '|' . $targets['ink'] . '|' . $targets['accent'] . '|' . $targets['background'] );
    }

    private function find_variant( string $key ): int {
        $ids = get_posts(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_key'       => self::VARIANT_META_KEY,
                'meta_value'     => $key,
            )
        );

        foreach ( $ids as $id ) {
            $file = get_attached_file( (int) $id );

            if ( is_string( $file ) && file_exists( $file ) ) {
                return (int) $id;
            }
        }

        return 0;
    }

    private function create_variant( int $original_id, array $targets, string $key ): int {
        $source = get_attached_file( $original_id );

        if ( ! is_string( $source ) || ! file_exists( $source ) || ! LogoRecolor::is_supported() ) {
            return 0;
        }

        wp_raise_memory_limit( 'image' );

        $png = $this->recolor->recolor( $source, $targets['ink'], $targets['accent'], $targets['background'] );

        if ( $png === null ) {
            return 0;
        }

        $upload = wp_upload_bits( 'logo-' . substr( $key, 0, 8 ) . '.png', null, $png );

        if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
            return 0;
        }

        $title         = get_the_title( $original_id );
        $attachment_id = wp_insert_attachment(
            array(
                'post_mime_type' => 'image/png',
                'post_title'     => ( $title !== '' ? $title : 'Logo' ) . ' (palette variant)',
                'post_content'   => '',
                'post_status'    => 'inherit',
                'meta_input'     => array(
                    self::VARIANT_META_KEY => $key,
                ),
            ),
            $upload['file']
        );

        if ( is_wp_error( $attachment_id ) || (int) $attachment_id === 0 ) {
            wp_delete_file( $upload['file'] );

            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata(
            $attachment_id,
            wp_generate_attachment_metadata( $attachment_id, $upload['file'] )
        );

        return (int) $attachment_id;
    }
}
