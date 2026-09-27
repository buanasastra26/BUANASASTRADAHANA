<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Builder\Dto\ColorPaletteDto;
use Hostinger\AiTheme\Builder\Elementor\KitManager;
use Hostinger\AiTheme\Constants\ApiRoutes;

defined( 'ABSPATH' ) || exit;

class Colors {

    use ColorUtils;
    use SoftwareIdTrait;

    /**
     * @var string
     */
    private string $description;

    /**
     * @var RequestClient
     */
    private RequestClient $request_client;

    private KitManager $kit_manager;
    private RequestClient $wh_api_client;

    public function __construct( string $description ) {
        $this->description = $description;
    }

    /**
     * @param RequestClient $request_client
     *
     * @return void
     */
    public function setRequestClient( RequestClient $request_client ): void {
        $this->request_client = $request_client;
    }

    public function setWhApiClient( RequestClient $wh_api_client ): void {
        $this->wh_api_client = $wh_api_client;
    }

    public function setKitManager( KitManager $kit_manager ): void {
        $this->kit_manager = $kit_manager;
    }

    public function generate_colors(): bool {
        if ( Helper::should_render_india_version() ) {
	        $colors = $this->get_india_palette();
        } else {
            $colors = $this->fetch_colors_from_api( 1 );
        }

        if ( empty( $colors ) ) {
            return false;
        }

        $first_palette = array_shift( $colors );
        if ( empty( $first_palette['color1'] ) ) {
            return false;
        }

        return $this->set_colors( $first_palette );
    }

    public function generate_color_options(): array {
        delete_option( 'hostinger_ai_colors' );

        $color_options = $this->fetch_colors_from_api( 6 );

        if ( ! empty( $color_options ) ) {
            update_option( 'hostinger_ai_color_options', $color_options, true );

            $first_color = $color_options[0] ?? null;
            if ( ! empty( $first_color['color1'] ) ) {
                $this->set_colors( $first_color );
            }
        }

        return $color_options ?? array();
    }

    public function fetch_colors_from_api( int $number_of_palettes = 1 ): ?array {
        $software_id = $this->get_software_id();
        if ( empty( $software_id ) ) {
            return null;
        }

        $params = array(
            'description'      => $this->description,
            'gradients'        => array(
                'z48lj' => 1,
            ),
            'numberOfPalettes' => $number_of_palettes,
            'style'            => 'auto',
        );

        $colors = $this->wh_api_client->post( ApiRoutes::INSTALLATIONS_BASE . $software_id . '/content/colors', $params );
        if ( ! empty( $colors ) ) {
            foreach ( $colors as &$color ) {
                if ( empty( $color['color1'] ) ) {
                    continue;
                }

                $color = $this->derive_dark_section_color( $color );
                $color = $this->ensure_accessible_contrast_ratio( $color );
            }

            return $colors;
        }

        return null;
    }

    public function set_colors( array $colors ): bool {
        if ( empty( $colors['color1'] ) ) {
            return false;
        }

        $colors = $this->derive_dark_section_color( $colors );
        $colors = $this->ensure_color_contrast( $colors, 'color2', 'light' );

        update_option( 'hostinger_ai_colors', $colors, true );

        $builder_type = get_option( 'hostinger_ai_builder_type', 'gutenberg' );
	    if ( get_option( 'hostinger_ai_woo', false ) ) {
		    ( new WooBuilder() )->boot();
	    }

        if ( $builder_type === 'elementor' ) {
            $elementor_builder = new ElementorBuilder();
            $elementor_builder->boot();

            $kit_manager = new KitManager();
            $kit_manager->transform_color_palette( ColorPaletteDto::from_array( $colors ) );
        }

		$reach_builder = new HostingerReachBuilder();
		$reach_builder->boot();

        return true;
    }

    /**
     * Overwrites color2 with a dark variant of color3, so section backgrounds
     * always derive from the palette's button (brand) color.
     */
    private function derive_dark_section_color( array $color_palette ): array {
        $color3 = $color_palette['color3'] ?? '';

        if ( ! is_string( $color3 ) || ! $this->is_valid_hex_color( $color3 ) ) {
            return $color_palette;
        }

        $color_palette['color2'] = $this->get_dark_variant( $color3 );

        return $color_palette;
    }

    private function ensure_accessible_contrast_ratio( array $color_palette ): array {

        $color_pairs = array(
            'color1' => 'dark',
            'color2' => 'light',
            'color3' => 'light',
        );

        foreach ( $color_pairs as $background => $foreground ) {
            $color_palette = $this->ensure_color_contrast( $color_palette, $background, $foreground );
        }

        return $color_palette;
    }

    private function ensure_color_contrast( array $color_palette, string $background, string $foreground ): array {
        $background_color = $color_palette[ $background ] ?? '';
        $foreground_color = $color_palette[ $foreground ] ?? '';

        if ( ! $this->is_valid_hex_color( $background_color ) || ! $this->is_valid_hex_color( $foreground_color ) ) {
            return $color_palette;
        }

        $current_contrast = $this->calculate_contrast_ratio( $background_color, $foreground_color );

        if ( $current_contrast < $this->get_required_contrast_ratio() ) {
            $adjusted_color = $this->adjust_color_for_contrast( $background_color, $foreground_color );
            $new_contrast   = $this->calculate_contrast_ratio( $adjusted_color, $foreground_color );

            do_action(
                'hostinger_ai_color_contrast_adjusted',
                $background,
                $background_color,
                $adjusted_color,
                $current_contrast,
                $new_contrast
            );

            $color_palette[ $background ] = $adjusted_color;
        }

        return $color_palette;
    }
}
