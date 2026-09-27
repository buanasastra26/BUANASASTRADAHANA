<?php

namespace Hostinger\AiTheme\Builder\Elementor;

use Elementor\Core\Kits\Manager as ElementorKitsManager;
use Elementor\Plugin as ElementorPlugin;
use Hostinger\AiTheme\Builder\Dto\ColorPaletteDto;
use Hostinger\AiTheme\Builder\Helper;

class KitManager {
    // Every opaque kit identifier lives in KitIds; these are aliases so callers
    // that already reference KitManager:: keep working.
    public const CUSTOM_TYPOGRAPHY_KEY = KitIds::CUSTOM_TYPOGRAPHY_KEY;
    public const CUSTOM_COLORS_KEY     = KitIds::CUSTOM_COLORS_KEY;

    public const H1_TYPOGRAPHY_ID         = KitIds::H1_TYPOGRAPHY;
    public const H2_TYPOGRAPHY_ID         = KitIds::H2_TYPOGRAPHY;
    public const H1_REGULAR_TYPOGRAPHY_ID = KitIds::H1_REGULAR_TYPOGRAPHY;
    public const BODY_TYPOGRAPHY_ID       = KitIds::BODY_TYPOGRAPHY;
    public const BODY_BOLD_TYPOGRAPHY_ID  = KitIds::BODY_BOLD_TYPOGRAPHY;
    public const BUTTON_TYPOGRAPHY_ID     = KitIds::BUTTON_TYPOGRAPHY;

    public const HEADING_TYPOGRAPHY_IDS = KitIds::HEADING_TYPOGRAPHY;
    public const BODY_TYPOGRAPHY_IDS    = KitIds::BODY_TYPOGRAPHY_GROUP;

    public const PAGE_BACKGROUND_ID = KitIds::PAGE_BACKGROUND;
    public const PAGE_TEXT_ID       = KitIds::PAGE_TEXT;
    public const V2_HEADING_FONT_ID = KitIds::V2_HEADING_FONT;
    public const V2_BODY_FONT_ID    = KitIds::V2_BODY_FONT;
    public const V2_BUTTON_FONT_ID  = KitIds::V2_BUTTON_FONT;
    public const TOKEN_COLOR_IDS    = KitIds::TOKEN_COLORS;

    private const ACTIVE_KIT_OPTION = 'elementor_active_kit';

    private int $kit_id;
    private array $kit_settings = array();

    public function __construct() {
        $this->kit_id = self::active_kit_id();

        if ($this->kit_id) {
            $settings = get_post_meta($this->kit_id, '_elementor_page_settings', true);
            $this->kit_settings = is_array($settings) ? $settings : [];

            if ( empty( $this->kit_settings[ self::CUSTOM_COLORS_KEY ] ) ) {
                $this->transform_color_palette( self::get_default_palette() );
            }
        }
    }

    public function set_custom_colors( array $colors ): bool {
        $this->kit_settings[ self::CUSTOM_COLORS_KEY ] = $colors;

        return $this->save();
    }

    public function apply_globals( array $custom_colors, array $custom_typography = array() ): bool {
        if ( ! empty( $custom_colors ) ) {
            $this->kit_settings[ self::CUSTOM_COLORS_KEY ] = $custom_colors;
        }

        if ( ! empty( $custom_typography ) ) {
            $this->kit_settings[ self::CUSTOM_TYPOGRAPHY_KEY ] = $custom_typography;
        }

        return $this->save();
    }

    public function apply_token_colors( array $tokens ): bool {
        $colors = array_column( $this->kit_settings[ self::CUSTOM_COLORS_KEY ] ?? array(), null, '_id' );

        foreach ( self::TOKEN_COLOR_IDS as $slug => $id ) {
            if ( empty( $tokens[ $slug ] ) ) {
                continue;
            }

            $colors[ $id ] = array(
                '_id'   => $id,
                'title' => ucwords( str_replace( '-', ' ', $slug ) ),
                'color' => $tokens[ $slug ],
            );
        }

        $this->kit_settings[ self::CUSTOM_COLORS_KEY ] = array_values( $colors );

        return $this->save();
    }

    // Update font families without replacing generated sizes and weights.
    public function apply_typography_families(
        string $heading_font,
        string $body_font,
        array $custom_typography = array(),
        bool $include_family_globals = true
    ): bool {
        $heading = trim( explode( ',', $heading_font )[0] );
        $body    = trim( explode( ',', $body_font )[0] );

        if ( ! empty( $custom_typography ) ) {
            $this->kit_settings[ self::CUSTOM_TYPOGRAPHY_KEY ] = $custom_typography;
        }

        $typography = $this->kit_settings[ self::CUSTOM_TYPOGRAPHY_KEY ] ?? array();
        foreach ( $typography as &$entry ) {
            $id = $entry['_id'] ?? '';

            if ( in_array( $id, self::HEADING_TYPOGRAPHY_IDS, true ) ) {
                $entry['typography_font_family'] = $heading;
            }

            if ( in_array( $id, self::BODY_TYPOGRAPHY_IDS, true ) ) {
                $entry['typography_font_family'] = $body;
            }
        }
        unset( $entry );

        if ( $include_family_globals ) {
            $typography_by_id = array_column( $typography, null, '_id' );
            $family_globals   = array(
                self::V2_HEADING_FONT_ID => array( 'title' => 'Generated heading font', 'family' => $heading ),
                self::V2_BODY_FONT_ID    => array( 'title' => 'Generated body font', 'family' => $body ),
                self::V2_BUTTON_FONT_ID  => array( 'title' => 'Generated button font', 'family' => $body ),
            );

            foreach ( $family_globals as $id => $global ) {
                $typography_by_id[ $id ] = array(
                    '_id'                    => $id,
                    'title'                  => $global['title'],
                    'typography_typography'  => 'custom',
                    'typography_font_family' => $global['family'],
                );
            }
            $typography = array_values( $typography_by_id );
        }

        $this->kit_settings[ self::CUSTOM_TYPOGRAPHY_KEY ]      = $typography;
        $this->apply_heading_typography_styles();
        $this->kit_settings['body_typography_typography']       = 'custom';
        $this->kit_settings['body_typography_font_family']      = $body;
        $this->kit_settings['button_typography_typography']     = 'custom';
        $this->kit_settings['button_typography_font_family']    = $body;

        foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $heading_level ) {
            $this->kit_settings[ $heading_level . '_typography_typography' ]  = 'custom';
            $this->kit_settings[ $heading_level . '_typography_font_family' ] = $heading;
        }

        update_option( 'hostinger_ai_body_font', $body_font );
        update_option( 'hostinger_elementor_typography_set', $heading_font );

        return $this->save();
    }

    public function set_page_title_selector( string $selector ): bool {
        $this->kit_settings['page_title_selector'] = $selector;

        return $this->save();
    }

	// WRDP-6936: 8px is the house corner radius shared with buttons, cards and
	// the compiled image markup, so every element rounds the same across pages.
	public function set_global_image_border_radius( int $radius = 8 ): bool {
		$this->kit_settings['image_border_radius'] = array(
			'unit'     => 'px',
			'top'      => (string) $radius,
			'right'    => (string) $radius,
			'bottom'   => (string) $radius,
			'left'     => (string) $radius,
			'isLinked' => true,
		);

		return $this->save();
	}

	public function set_global_container_mobile_layout_padding( float $padding = 1 ): bool {
		$this->kit_settings['container_padding_mobile'] = array(
			'unit'     => 'rem',
			'top'      => (string) $padding,
			'right'    => (string) $padding,
			'bottom'   => (string) $padding,
			'left'     => (string) $padding,
			'isLinked' => true,
		);

		return $this->save();
	}

    public function transform_color_palette( ColorPaletteDto $color_palette ) : bool {
        $colors = [
            [
                '_id' => KitIds::COLOR_1,
                'title' => 'Color 1 (Section backgrounds)',
                'color' => $color_palette->get_color_1() ?? ''
            ],
            [
                '_id' => KitIds::COLOR_2,
                'title' => 'Color 2 (Section backgrounds)',
                'color' => $color_palette->get_color_2() ?? ''
            ],
            [
                '_id' => KitIds::COLOR_3,
                'title' => 'Color 3 (Button background)',
                'color' => $color_palette->get_color_3() ?? ''
            ],
            [
                '_id' => KitIds::LIGHT,
                'title' => 'Light (Text on Color 2 and Gradient)',
                'color' => $color_palette->get_light() ?? ''
            ],
            [
                '_id' => KitIds::DARK,
                'title' => 'Dark (Text on Light and Color 1)',
                'color' => $color_palette->get_dark() ?? ''
            ],
            [
                '_id' => KitIds::GREY,
                'title' => 'Grey (Form borders)',
                'color' => $color_palette->get_grey() ?? ''
            ],
            [
                '_id' => KitIds::GRADIENT_COLOR_1,
                'title' => 'Gradient color 1',
                'color' => $color_palette->get_main_gradient() ? $color_palette->get_main_gradient()->get_main_color() : ''
            ],
            [
                '_id'   => self::PAGE_BACKGROUND_ID,
                'title' => 'Page background',
                'color' => $color_palette->get_page_background() ?: '#ffffff',
            ],
            [
                '_id'   => self::PAGE_TEXT_ID,
                'title' => 'Page text',
                'color' => $color_palette->get_page_text() ?: '#000000',
            ],
        ];

        $this->kit_settings['background_background'] = 'classic';
        $this->kit_settings['background_color']      = $color_palette->get_page_background() ?: '#ffffff';

        return $this->set_custom_colors( $this->merge_custom_colors( $colors ) );
    }

    // Replacing the array dropped token ids; Elementor then falls back to stock primary/accent.
    private function merge_custom_colors( array $colors ): array {
        $merged = array_column( $this->kit_settings[ self::CUSTOM_COLORS_KEY ] ?? array(), null, '_id' );

        foreach ( $colors as $color ) {
            $merged[ $color['_id'] ] = $color;
        }

        return array_values( $merged );
    }

    public function get_custom_color_ids(): array {
        return array_column( $this->kit_settings[ self::CUSTOM_COLORS_KEY ] ?? array(), '_id' );
    }

    public function transform_custom_typography( string $heading_font, string $body_font ): bool {
        $heading = trim( explode( ',', $heading_font )[0] );
        $body    = trim( explode( ',', $body_font )[0] );

        $this->kit_settings[ self::CUSTOM_TYPOGRAPHY_KEY ] = [
            [
                '_id'                         => self::H1_TYPOGRAPHY_ID,
                'title'                       => 'H1 Heading',
                'typography_typography'       => 'custom',
                'typography_font_family'      => $heading,
                'typography_font_size'        => [ 'unit' => 'rem', 'size' => 3, 'sizes' => [] ],
                'typography_font_weight'      => '700',
            ],
            [
                '_id'                          => self::H2_TYPOGRAPHY_ID,
                'title'                        => 'H2 Heading',
                'typography_typography'        => 'custom',
                'typography_font_family'       => $heading,
                'typography_font_size'         => [ 'unit' => 'rem', 'size' => 2.5, 'sizes' => [] ],
                'typography_font_size_mobile'  => [ 'unit' => 'rem', 'size' => 1.9, 'sizes' => [] ],
                'typography_font_weight'       => 'bold',
            ],
            [
                '_id'                          => self::H1_REGULAR_TYPOGRAPHY_ID,
                'title'                        => 'H1 Heading (400)',
                'typography_typography'        => 'custom',
                'typography_font_family'       => $heading,
                'typography_font_size'         => [ 'unit' => 'rem', 'size' => 2.3, 'sizes' => [] ],
                'typography_font_size_mobile'  => [ 'unit' => 'rem', 'size' => 1.9, 'sizes' => [] ],
                'typography_font_weight'       => 'bold',
            ],
            [
                '_id'                         => self::BODY_TYPOGRAPHY_ID,
                'title'                       => 'Body',
                'typography_typography'       => 'custom',
                'typography_font_family'      => $body,
                'typography_font_size'        => [ 'unit' => 'rem', 'size' => 1, 'sizes' => [] ],
                'typography_font_weight'      => '400',
            ],
            [
                '_id'                         => self::BODY_BOLD_TYPOGRAPHY_ID,
                'title'                       => 'Body Bold',
                'typography_typography'       => 'custom',
                'typography_font_family'      => $body,
                'typography_font_size'        => [ 'unit' => 'rem', 'size' => 1, 'sizes' => [] ],
                'typography_font_weight'      => '700',
            ],
            [
                '_id'                         => self::BUTTON_TYPOGRAPHY_ID,
                'title'                       => 'Button',
                'typography_typography'       => 'custom',
                'typography_font_family'      => $body,
                'typography_font_size'        => [ 'unit' => 'rem', 'size' => 1, 'sizes' => [] ],
                'typography_font_weight'      => '500',
            ],
        ];

        return $this->apply_typography_families( $heading_font, $body_font, array(), false );
    }

    // Copies the generated H1/H2 entry sizes and weights onto the kit's own heading defaults.
    private function apply_heading_typography_styles(): void {
        $typography       = $this->kit_settings[ self::CUSTOM_TYPOGRAPHY_KEY ] ?? array();
        $typography_by_id = array_column( $typography, null, '_id' );
        $heading_entries  = array(
            'h1' => self::H1_TYPOGRAPHY_ID,
            'h2' => self::H2_TYPOGRAPHY_ID,
        );
        $metric_names = array(
            'font_size',
            'font_size_mobile',
            'font_weight',
            'line_height',
            'letter_spacing',
        );

        foreach ( $heading_entries as $heading_level => $typography_id ) {
            $entry = $typography_by_id[ $typography_id ] ?? array();

            foreach ( $metric_names as $metric_name ) {
                $source_key = 'typography_' . $metric_name;

                if ( ! array_key_exists( $source_key, $entry ) ) {
                    continue;
                }

                $this->kit_settings[ $heading_level . '_typography_' . $metric_name ] = $entry[ $source_key ];
            }
        }
    }

    private static function active_kit_id(): int {
        $kit_id = (int) get_option( self::ACTIVE_KIT_OPTION );

        return $kit_id > 0 && get_post_status( $kit_id ) !== false ? $kit_id : 0;
    }

    private static function provision_kit(): int {
        if ( ! class_exists( ElementorKitsManager::class ) ) {
            return 0;
        }

        delete_option( self::ACTIVE_KIT_OPTION );

        $kit_id = (int) ElementorKitsManager::create_default_kit();

        if ( $kit_id <= 0 ) {
            delete_option( self::ACTIVE_KIT_OPTION );
            Helper::log( 'Elementor kit could not be created for the active site.' );

            return 0;
        }

        return $kit_id;
    }

    private function save(): bool {
        if ( empty( $this->kit_id ) ) {
            $this->kit_id = self::provision_kit();
        }

        if ( empty( $this->kit_id ) ) {
            Helper::log( 'Elementor kit settings were dropped: no active kit could be resolved.' );

            return false;
        }

        $result = update_post_meta( $this->kit_id, '_elementor_page_settings', $this->kit_settings );

        if ( class_exists( ElementorPlugin::class ) ) {
            $elementor = ElementorPlugin::instance();
            if ( ! empty( $elementor->files_manager ) && method_exists( $elementor->files_manager, 'clear_cache' ) ) {
                $elementor->files_manager->clear_cache();
            }
        }

        return $result;
    }

    private static function get_default_palette(): ColorPaletteDto {
        return ColorPaletteDto::from_array( [
            'color1'    => '#F6F7F9',
            'color2'    => '#23272F',
            'color3'    => '#70777f',
            'light'     => '#ffffff',
            'dark'      => '#0d141a',
            'grey'      => '#B8C0CC',
            'gradients' => [
                'z48lj' => [
                    'gradient' => '#A8BFE4',
                ],
            ],
        ] );
    }
}
