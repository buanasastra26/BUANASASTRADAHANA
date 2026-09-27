<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Builder\Dto\ColorPaletteDto;
use Hostinger\AiTheme\Builder\Elementor\KitManager;

defined( 'ABSPATH' ) || exit;

class EnvelopeCustomization {
    private const ORIGINAL_OPTION_ID = 'original';

    // How far each soft/alt token is tinted towards its counterpart colour, 0..1.
    private const SURFACE_ALT_TINT  = 0.08;
    private const INK_SOFT_TINT     = 0.35;
    private const PRIMARY_SOFT_TINT = 0.55;

    private const BACKGROUND_TOKEN_PRIORITY = array(
        'primary',
        'accent',
        'primary-soft',
        'accent-soft',
        'surface-alt',
        'surface',
        'base',
        'color3',
        'color2',
        'color1',
        'ink',
        'contrast',
    );

    private const FOREGROUND_TOKEN_PRIORITY = array(
        'contrast',
        'ink',
        'ink-soft',
        'ink-inverse',
        'primary',
        'accent',
        'dark',
        'grey',
        'light',
        'color3',
        'color2',
        'color1',
    );

    private const TOKEN_MAPPINGS = array(
        'color1'   => 'color1',
        'color2'   => 'color2',
        'color3'   => 'color3',
        'light'    => 'light',
        'dark'     => 'dark',
        'grey'     => 'grey',
        'base'     => 'page_background',
        'contrast' => 'page_text',
        'surface'  => 'page_background',
        'ink'      => 'page_text',
        'primary'  => 'color3',
    );

    public static function capture( array $theme, string $editor ): array {
        $gutenberg = is_array( $theme['gutenberg'] ?? null ) ? $theme['gutenberg'] : array();
        $elementor = is_array( $theme['elementor'] ?? null ) ? $theme['elementor'] : array();
        $tokens    = self::get_palette_by_slug( $gutenberg );
        $colors    = self::extract_colors_from_tokens( $tokens );
        $font_pair = self::extract_font_pair( $gutenberg, $elementor );

        self::persist_captured_theme( $gutenberg, $tokens, $colors, $font_pair );

        if ( $editor === 'elementor' ) {
            self::apply_captured_theme_to_kit( $colors, $tokens, $font_pair, $elementor );
        }

        return array(
            'colors'    => $colors,
            'font_pair' => $font_pair,
        );
    }

    private static function persist_captured_theme( array $gutenberg, array $tokens, array $colors, array $font_pair ): void {
        update_option( BundleSchema::OPTION_THEME_JSON, $gutenberg );
        update_option( BundleSchema::OPTION_ORIGINAL_TOKENS, $tokens );

        if ( ! empty( $colors['color1'] ) ) {
            update_option( BundleSchema::OPTION_ORIGINAL_COLORS, $colors );
            update_option( 'hostinger_ai_colors', $colors );
        }

        if ( ! empty( $font_pair['heading']['font_family'] ) && ! empty( $font_pair['body']['font_family'] ) ) {
            update_option( BundleSchema::OPTION_ORIGINAL_FONTS, $font_pair );
            update_option( 'hostinger_ai_font', $font_pair['heading']['font_family'] );
            update_option( 'hostinger_ai_body_font_override', $font_pair['body']['font_family'] );
            update_option( 'hostinger_ai_body_font', $font_pair['body']['font_family'] );
        }
    }

    private static function apply_captured_theme_to_kit( array $colors, array $tokens, array $font_pair, array $elementor ): void {
        if ( ! empty( $colors['color1'] ) ) {
            $kit_manager = new KitManager();
            $kit_manager->transform_color_palette( ColorPaletteDto::from_array( $colors ) );
            $kit_manager->apply_token_colors( self::kit_tokens( $tokens, $colors ) );
        }

        if ( empty( $font_pair['heading']['font_family'] ) ) {
            return;
        }

        $custom_typography = $elementor[ KitManager::CUSTOM_TYPOGRAPHY_KEY ] ?? null;

        ( new KitManager() )->apply_typography_families(
            $font_pair['heading']['font_family'],
            $font_pair['body']['font_family'],
            is_array( $custom_typography ) ? $custom_typography : array()
        );
    }

    public static function bind_elementor_globals( array $content ): array {
        $tokens = get_option( BundleSchema::OPTION_ORIGINAL_TOKENS, array() );

        return self::bind_elementor_nodes( $content, self::elementor_resolvable_tokens( is_array( $tokens ) ? $tokens : array() ) );
    }

    private static function elementor_resolvable_tokens( array $tokens ): array {
        $available = ( new KitManager() )->get_custom_color_ids();

        return array_filter(
            $tokens,
            static function ( string $slug ) use ( $available ): bool {
                $id = KitManager::TOKEN_COLOR_IDS[ $slug ] ?? '';

                return $id !== '' && in_array( $id, $available, true );
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function update_palette( array $colors ): bool {
        $theme_json = self::get_theme_json();
        $palette    = $theme_json['settings']['color']['palette'] ?? null;

        if ( ! is_array( $palette ) ) {
            return false;
        }

        $tokens_from_colors = self::get_tokens_from_colors( $colors );

        foreach ( $palette as &$entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            $slug = $entry['slug'] ?? '';

            if ( isset( self::TOKEN_MAPPINGS[ $slug ] ) ) {
                $source = self::TOKEN_MAPPINGS[ $slug ];
                $color  = self::get_color( $colors, $source );

                if ( $color !== '' ) {
                    $entry['color'] = $color;
                }

                continue;
            }

            if ( isset( $tokens_from_colors[ $slug ] ) ) {
                $entry['color'] = $tokens_from_colors[ $slug ];
            }
        }
        unset( $entry );

        $theme_json['settings']['color']['palette'] = $palette;

        update_option( BundleSchema::OPTION_THEME_JSON, $theme_json, false );

        if ( get_option( 'hostinger_ai_builder_type' ) === 'elementor' ) {
            ( new KitManager() )->apply_token_colors( $tokens_from_colors );
        }

        return true;
    }

    public static function update_typography( string $heading_font, string $body_font, array $theme_fonts ): bool {
        $heading_slug = self::find_font_slug( $heading_font, $theme_fonts );
        $body_slug    = self::find_font_slug( $body_font, $theme_fonts );

        if ( $heading_slug === '' || $body_slug === '' ) {
            return false;
        }

        $theme_json = self::get_theme_json();

        $heading_family = 'var:preset|font-family|' . $heading_slug;
        $body_family    = 'var:preset|font-family|' . $body_slug;

        $theme_json['styles']['typography']['fontFamily'] = $body_family;

        $theme_json['styles']['elements']['heading']['typography']['fontFamily'] = $heading_family;

        foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $heading_level ) {
            $theme_json['styles']['elements'][ $heading_level ]['typography']['fontFamily'] = $heading_family;
        }

        $theme_json['styles']['blocks']['core/site-title']['typography']['fontFamily'] = $heading_family;

        update_option( BundleSchema::OPTION_THEME_JSON, $theme_json, false );

        return true;
    }

    public static function update_elementor_heading_fonts( string $heading_font ): void {
        $font_family = trim( explode( ',', $heading_font )[0] );

        if ( $font_family === '' ) {
            return;
        }

        foreach ( (array) get_option( BundleSchema::OPTION_PAGES, array() ) as $page_id ) {
            $elementor_json = get_post_meta( (int) $page_id, '_elementor_data', true );
            $content        = is_array( $elementor_json )
                ? $elementor_json
                : json_decode( is_string( $elementor_json ) ? $elementor_json : '', true );

            if ( ! is_array( $content ) ) {
                continue;
            }

            $changed = false;
            $content = self::update_elementor_heading_nodes( $content, $font_family, $changed );

            if ( $changed ) {
                Helper::save_elementor_data( (int) $page_id, (string) wp_json_encode( $content ) );
            }
        }
    }

    private static function get_palette_by_slug( array $gutenberg ): array {
        $tokens = array();

        foreach ( $gutenberg['settings']['color']['palette'] ?? array() as $entry ) {
            $slug  = $entry['slug'] ?? '';
            $color = $entry['color'] ?? '';

            if ( ! is_string( $slug ) || $slug === '' || ! is_string( $color ) || $color === '' ) {
                continue;
            }

            $color = strtolower( self::normalize_color( $color ) );

            if ( $color === '' ) {
                continue;
            }

            $tokens[ $slug ] = $color;
        }

        return $tokens;
    }

    private static function extract_colors_from_tokens( array $tokens ): array {
        return array(
            'color1'          => $tokens['color1'] ?? ( $tokens['surface'] ?? '' ),
            'color2'          => $tokens['color2'] ?? ( $tokens['ink'] ?? '' ),
            'color3'          => $tokens['color3'] ?? ( $tokens['primary'] ?? '' ),
            'light'           => $tokens['light'] ?? ( $tokens['ink-inverse'] ?? '' ),
            'dark'            => $tokens['dark'] ?? ( $tokens['ink'] ?? '' ),
            'grey'            => $tokens['grey'] ?? ( $tokens['ink-soft'] ?? '' ),
            'gradients'       => array(
                'gradient-one' => array(
                    'gradient' => $tokens['accent'] ?? ( $tokens['color3'] ?? '' ),
                ),
            ),
            'page_background' => $tokens['page-background'] ?? ( $tokens['base'] ?? ( $tokens['surface'] ?? '' ) ),
            'page_text'       => $tokens['page-text'] ?? ( $tokens['contrast'] ?? ( $tokens['ink'] ?? '' ) ),
        );
    }

    private static function extract_font_pair( array $gutenberg, $elementor ): array {
        $theme_fonts = self::get_theme_fonts();
        $heading     = self::resolve_font_family(
            (string) ( $gutenberg['styles']['elements']['heading']['typography']['fontFamily'] ?? '' ),
            $theme_fonts
        );
        $body        = self::resolve_font_family(
            (string) ( $gutenberg['styles']['typography']['fontFamily'] ?? '' ),
            $theme_fonts
        );

        if ( ( $heading === '' || $body === '' ) && is_array( $elementor ) ) {
            self::fill_fonts_from_elementor_typography( $elementor, $theme_fonts, $heading, $body );
        }

        return array(
            'id'      => self::ORIGINAL_OPTION_ID,
            'heading' => array(
                'name'        => self::get_font_name( $heading, $theme_fonts ),
                'font_family' => $heading,
                'url'         => '',
            ),
            'body'    => array(
                'name'        => self::get_font_name( $body, $theme_fonts ),
                'font_family' => $body,
                'url'         => '',
            ),
        );
    }

    private static function fill_fonts_from_elementor_typography(
        array $elementor,
        array $theme_fonts,
        string &$heading,
        string &$body
    ): void {
        foreach ( $elementor[ KitManager::CUSTOM_TYPOGRAPHY_KEY ] ?? array() as $entry ) {
            $id     = $entry['_id'] ?? '';
            $family = (string) ( $entry['typography_font_family'] ?? '' );

            if ( $heading === '' && in_array( $id, KitManager::HEADING_TYPOGRAPHY_IDS, true ) ) {
                $heading = self::resolve_font_family( $family, $theme_fonts );
            }

            if ( $body === '' && in_array( $id, KitManager::BODY_TYPOGRAPHY_IDS, true ) ) {
                $body = self::resolve_font_family( $family, $theme_fonts );
            }
        }
    }

    private static function get_theme_fonts(): array {
        $settings = wp_get_global_settings();

        return is_array( $settings['typography']['fontFamilies']['theme'] ?? null )
            ? $settings['typography']['fontFamilies']['theme']
            : array();
    }

    private static function resolve_font_family( string $font, array $theme_fonts ): string {
        if ( str_starts_with( $font, 'var:preset|font-family|' ) ) {
            $slug = substr( $font, strlen( 'var:preset|font-family|' ) );

            foreach ( $theme_fonts as $theme_font ) {
                if ( ( $theme_font['slug'] ?? '' ) === $slug ) {
                    return (string) ( $theme_font['fontFamily'] ?? '' );
                }
            }
        }

        foreach ( $theme_fonts as $theme_font ) {
            $family = (string) ( $theme_font['fontFamily'] ?? '' );
            $name   = (string) ( $theme_font['name'] ?? '' );

            if ( strcasecmp( $font, $family ) === 0 || strcasecmp( $font, $name ) === 0 ) {
                return $family;
            }
        }

        return $font;
    }

    private static function get_font_name( string $font_family, array $theme_fonts ): string {
        foreach ( $theme_fonts as $theme_font ) {
            if ( ( $theme_font['fontFamily'] ?? '' ) === $font_family ) {
                return (string) ( $theme_font['name'] ?? '' );
            }
        }

        return trim( explode( ',', $font_family )[0] );
    }

    private static function get_theme_json(): array {
        $theme_json = get_option( BundleSchema::OPTION_THEME_JSON, array() );

        return is_array( $theme_json ) ? $theme_json : array();
    }

    private static function kit_tokens( array $tokens, array $colors ): array {
        $kit_tokens = self::get_tokens_from_colors( $colors );

        foreach ( array_keys( KitManager::TOKEN_COLOR_IDS ) as $slug ) {
            $color = is_string( $tokens[ $slug ] ?? null ) ? self::normalize_color( $tokens[ $slug ] ) : '';

            if ( $color !== '' ) {
                $kit_tokens[ $slug ] = $color;
            }
        }

        return $kit_tokens;
    }

    private static function get_tokens_from_colors( array $colors ): array {
        $surface = self::get_color( $colors, 'page_background' );
        $ink     = self::get_color( $colors, 'page_text' );
        $primary = self::get_color( $colors, 'color3' );

        $tokens = array(
            'color1'       => self::get_color( $colors, 'color1' ),
            'color2'       => self::get_color( $colors, 'color2' ),
            'color3'       => $primary,
            'light'        => self::get_color( $colors, 'light' ),
            'dark'         => self::get_color( $colors, 'dark' ),
            'grey'         => self::get_color( $colors, 'grey' ),
            'base'         => $surface,
            'contrast'     => $ink,
            'surface'      => $surface,
            'surface-alt'  => self::tint_towards( $surface, $ink, self::SURFACE_ALT_TINT ),
            'ink'          => $ink,
            'ink-soft'     => self::tint_towards( $ink, $surface, self::INK_SOFT_TINT ),
            'ink-inverse'  => $surface,
            'primary'      => $primary,
            'primary-soft' => self::tint_towards( $primary, $surface, self::PRIMARY_SOFT_TINT ),
        );

        return array_filter( $tokens );
    }

    private static function bind_elementor_nodes( array $nodes, array $tokens ): array {
        foreach ( $nodes as &$node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }

            if ( is_array( $node['settings'] ?? null ) ) {
                $node['settings'] = self::bind_elementor_settings(
                    $node['settings'],
                    (string) ( $node['widgetType'] ?? '' ),
                    $tokens
                );
            }

            if ( is_array( $node['elements'] ?? null ) ) {
                $node['elements'] = self::bind_elementor_nodes( $node['elements'], $tokens );
            }
        }
        unset( $node );

        return $nodes;
    }

    private static function update_elementor_heading_nodes( array $nodes, string $font_family, bool &$changed ): array {
        foreach ( $nodes as &$node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }

            if ( ( $node['widgetType'] ?? '' ) === 'heading' && is_array( $node['settings'] ?? null ) ) {
                $globals = is_array( $node['settings']['__globals__'] ?? null )
                    ? $node['settings']['__globals__']
                    : array();

                if ( empty( $globals['typography_typography'] )
                    && ( $node['settings']['typography_font_family'] ?? '' ) !== $font_family
                ) {
                    $node['settings']['typography_typography']  = 'custom';
                    $node['settings']['typography_font_family'] = $font_family;
                    $changed                                    = true;
                }
            }

            if ( is_array( $node['elements'] ?? null ) ) {
                $node['elements'] = self::update_elementor_heading_nodes(
                    $node['elements'],
                    $font_family,
                    $changed
                );
            }
        }
        unset( $node );

        return $nodes;
    }

    private static function bind_elementor_settings( array $settings, string $widget_type, array $tokens ): array {
        $globals = is_array( $settings['__globals__'] ?? null ) ? $settings['__globals__'] : array();

        foreach ( $settings as $key => $value ) {
            if ( $key === '__globals__' || ! is_string( $value ) ) {
                continue;
            }

            $token = self::find_token_for_control( $key, $value, $tokens );
            if ( $token !== '' && isset( KitManager::TOKEN_COLOR_IDS[ $token ] ) ) {
                $globals[ $key ] = 'globals/colors?id=' . KitManager::TOKEN_COLOR_IDS[ $token ];
            }
        }

        if ( ! isset( $globals['typography_typography'] ) ) {
            $typography_id = self::get_typography_id( $widget_type );
            if ( $typography_id !== '' ) {
                $globals['typography_typography'] = 'globals/typography?id=' . $typography_id;
            }
        }

        if ( ! empty( $globals ) ) {
            $settings['__globals__'] = $globals;
        }

        return $settings;
    }

    private static function find_token_for_control( string $key, string $value, array $tokens ): string {
        $matches = array();

        foreach ( $tokens as $slug => $color ) {
            if ( strcasecmp( $value, $color ) === 0 ) {
                $matches[] = $slug;
            }
        }

        $priority = str_contains( $key, 'background' )
            ? self::BACKGROUND_TOKEN_PRIORITY
            : self::FOREGROUND_TOKEN_PRIORITY;

        foreach ( $priority as $slug ) {
            if ( in_array( $slug, $matches, true ) ) {
                return $slug;
            }
        }

        return $matches[0] ?? '';
    }

    private static function get_typography_id( string $widget_type ): string {
        if ( $widget_type === 'button' ) {
            return KitManager::V2_BUTTON_FONT_ID;
        }

        if ( $widget_type === 'text-editor' ) {
            return KitManager::V2_BODY_FONT_ID;
        }

        return '';
    }

    private static function tint_towards( string $base, string $target, float $factor ): string {
        if ( $base === '' || $target === '' ) {
            return '';
        }

        $base_rgb   = sscanf( $base, '#%02x%02x%02x' );
        $target_rgb = sscanf( $target, '#%02x%02x%02x' );
        $mixed      = array();

        foreach ( array( 0, 1, 2 ) as $index ) {
            $mixed[] = (int) ( $base_rgb[ $index ] + ( $target_rgb[ $index ] - $base_rgb[ $index ] ) * $factor );
        }

        return sprintf( '#%02x%02x%02x', ...$mixed );
    }

    private static function find_font_slug( string $font_family, array $theme_fonts ): string {
        foreach ( $theme_fonts as $font ) {
            if ( ( $font['fontFamily'] ?? '' ) === $font_family ) {
                return sanitize_title( $font['slug'] ?? '' );
            }
        }

        return '';
    }

    private static function get_color( array $colors, string $key ): string {
        $camel_key = lcfirst( str_replace( '_', '', ucwords( $key, '_' ) ) );
        $value     = $colors[ $key ] ?? ( $colors[ $camel_key ] ?? '' );

        if ( ! is_string( $value ) ) {
            return '';
        }

        return self::normalize_color( $value );
    }

    private static function normalize_color( string $value ): string {
        $sanitized = sanitize_hex_color( self::normalize_hex( $value ) );

        return $sanitized ? $sanitized : '';
    }

    // sanitize_hex_color rejects 8-digit hex used for translucent roles.
    private static function normalize_hex( string $value ): string {
        $value = trim( $value );

        if ( preg_match( '/^#([0-9a-fA-F]{3})[0-9a-fA-F]?$/', $value, $matches ) ) {
            $value = '#' . preg_replace( '/./', '$0$0', $matches[1] );
        }

        if ( preg_match( '/^#([0-9a-fA-F]{6})[0-9a-fA-F]{2}$/', $value, $matches ) ) {
            return '#' . $matches[1];
        }

        return $value;
    }
}
