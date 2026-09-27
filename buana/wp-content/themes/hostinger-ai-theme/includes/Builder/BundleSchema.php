<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class BundleSchema {
    public const MODE_ENVELOPE = 'envelope';

    // Missing version is treated as supported; a different major is rejected.
    public const SUPPORTED_MAJOR = 1;

    public const SUPPORTED_EDITORS = array( 'gutenberg', 'elementor' );

    public const OPTION_MODE       = 'hostinger_ai_applier_mode';
    public const OPTION_THEME_JSON = 'hostinger_ai_envelope_theme_json';
    public const OPTION_ORIGINAL_COLORS = 'hostinger_ai_envelope_original_colors';
    public const OPTION_ORIGINAL_FONTS  = 'hostinger_ai_envelope_original_fonts';
    public const OPTION_ORIGINAL_LOGO   = 'hostinger_ai_envelope_original_logo_id';
    public const OPTION_ADAPTED_LOGO    = 'hostinger_ai_envelope_adapted_logo_id';
    public const OPTION_ORIGINAL_TOKENS = 'hostinger_ai_envelope_original_tokens';

    public const OPTION_PAGES          = 'hostinger_ai_envelope_pages';
    public const OPTION_CREATED_PAGES  = 'hostinger_ai_created_pages';
    public const OPTION_MENU           = 'hostinger_ai_envelope_menu_id';
    public const OPTION_BLOG_POSTS     = 'hostinger_ai_created_blog_posts';
    public const OPTION_BLOG_CATS      = 'hostinger_ai_created_blog_categories';
    public const OPTION_PRODUCTS       = 'hostinger_ai_created_products';
    public const OPTION_PRODUCT_CATS   = 'hostinger_ai_created_product_categories';

    public static function is_supported_version( $version ): bool {
        if ( $version === null || $version === '' ) {
            // Producer omits a version today; treat that as the current major.
            return true;
        }

        if ( ! is_string( $version ) && ! is_int( $version ) && ! is_float( $version ) ) {
            return false;
        }

        $major = (int) explode( '.', (string) $version )[0];

        return $major === self::SUPPORTED_MAJOR;
    }

    public static function validate( array $envelope ): bool {
        $version = $envelope['schema_version'] ?? ( $envelope['version'] ?? '' );
        if ( ! self::is_supported_version( $version ) ) {
            return false;
        }

        $editor = $envelope['editor'] ?? '';
        if ( ! is_string( $editor ) || ! in_array( $editor, self::SUPPORTED_EDITORS, true ) ) {
            return false;
        }

        if ( empty( $envelope['pages'] ) || ! is_array( $envelope['pages'] ) ) {
            return false;
        }

        if ( empty( $envelope['theme'] ) || ! is_array( $envelope['theme'] ) ) {
            return false;
        }

        return true;
    }

    public static function is_active(): bool {
        return get_option( self::OPTION_MODE ) === self::MODE_ENVELOPE;
    }
}
