<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

trait ColorUtils {

	public function get_india_palette(): array {
		return array(
			array(
				'color1' => '#FFF7ED',
				'color2' => '#006C4E',
				'color3' => '#F97316',
		        'light' => '#FFFFFF',
		        'dark' => '#1E1B15',
		        'grey' => '#B8C0CC',
		        'gradients' => array(
		        	'z48lj' => array(
		        		'gradient' => '#F6F4E9,#B03A2E,#FFC857,#2A1A0A',
		        	)
		        )
			)
		);
	}

    public function get_required_contrast_ratio(): float {
        return 4.6;
    }

    /**
     * Lightness used for dark section backgrounds derived from another colour.
     */
    public function get_dark_variant_lightness(): float {
        return 0.15;
    }

    /**
     * Returns a dark variant of the given colour, keeping its hue and saturation.
     */
    public function get_dark_variant( string $hex_color ): string {
        $hsl = $this->hex_to_hsl( $hex_color );

        $hsl['l'] = $this->get_dark_variant_lightness();

        return $this->hsl_to_hex( $hsl );
    }

    public function is_valid_hex_color( string $hex_color ): bool {
        return (bool) preg_match( '/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex_color );
    }

    public function calculate_contrast_ratio( string $color1, string $color2 ): float {
        $luminance1 = $this->get_luminance( $color1 );
        $luminance2 = $this->get_luminance( $color2 );

        $lighter = max( $luminance1, $luminance2 );
        $darker  = min( $luminance1, $luminance2 );

        return ( $lighter + 0.05 ) / ( $darker + 0.05 );
    }

    public function get_luminance( string $hex_color ): float {
        $rgb = $this->hex_to_rgb( $hex_color );

        $rgb_normalized = array_map(
            function ( $value ) {
                $value = $value / 255;

                return $value <= 0.03928
                ? $value / 12.92
                : pow( ( $value + 0.055 ) / 1.055, 2.4 );
            },
            $rgb
        );

        return 0.2126 * $rgb_normalized['r'] +
                0.7152 * $rgb_normalized['g'] +
                0.0722 * $rgb_normalized['b'];
    }

    public function adjust_color_for_contrast(
        string $background_color,
        string $foreground_color,
        ?float $contrast_ratio = null,
    ): string {
        $foreground_luminance = $this->get_luminance( $foreground_color );
        $target_luminance     = $this->calculate_target_luminance( $foreground_luminance, $contrast_ratio );

        return $this->adjust_color_to_luminance( $background_color, $target_luminance );
    }

    public function calculate_target_luminance( float $foreground_luminance, ?float $contrast_ratio = null ): float {
        $contrast_ratio = $contrast_ratio ?? $this->get_required_contrast_ratio();
        $lighter_target = ( $foreground_luminance + 0.05 ) * $contrast_ratio - 0.05;
        $darker_target  = ( $foreground_luminance + 0.05 ) / $contrast_ratio - 0.05;

        if ( $lighter_target <= 1.0 ) {
            return $lighter_target;
        }

        return max( 0.0, $darker_target );
    }

    public function adjust_color_to_luminance( string $hex_color, float $target_luminance ): string {
        $hsl = $this->hex_to_hsl( $hex_color );

        $min_lightness = 0.0;
        $max_lightness = 1.0;
        $tolerance     = 0.001;

        while ( ( $max_lightness - $min_lightness ) > $tolerance ) {
            $test_lightness = ( $min_lightness + $max_lightness ) / 2;
            $test_hsl       = array_merge( $hsl, array( 'l' => $test_lightness ) );
            $test_hex       = $this->hsl_to_hex( $test_hsl );
            $test_luminance = $this->get_luminance( $test_hex );

            if ( $test_luminance < $target_luminance ) {
                $min_lightness = $test_lightness;
            } else {
                $max_lightness = $test_lightness;
            }
        }

        $hsl = array_merge( $hsl, array( 'l' => ( $min_lightness + $max_lightness ) / 2 ) );

        return $this->hsl_to_hex( $hsl );
    }

    public function hex_to_rgb( string $hex ): array {
        $hex = ltrim( $hex, '#' );

        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return array(
            'r' => hexdec( substr( $hex, 0, 2 ) ),
            'g' => hexdec( substr( $hex, 2, 2 ) ),
            'b' => hexdec( substr( $hex, 4, 2 ) ),
        );
    }

    public function hex_to_hsl( string $hex ): array {
        $rgb = $this->hex_to_rgb( $hex );

        return $this->rgb_to_hsl( $rgb['r'], $rgb['g'], $rgb['b'] );
    }

    public function rgb_to_hsl( int $r, int $g, int $b ): array {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max  = max( $r, $g, $b );
        $min  = min( $r, $g, $b );
        $diff = $max - $min;

        $l = ( $max + $min ) / 2;

        if ( $diff == 0.0 ) {
            return array(
                'h' => 0.0,
                's' => 0.0,
                'l' => $l,
            );
        }

	    $sum = $max + $min;
	    if ( $l > 0.5 ) {
		    $den = 2 - $sum;
		    $s   = ( $den == 0.0 ) ? 0.0 : $diff / $den;
	    } else {
		    $den = $sum;
		    $s   = ( $den == 0.0 ) ? 0.0 : $diff / $den;
	    }

        switch ( $max ) {
            case $r:
                $h = ( ( $g - $b ) / $diff + ( $g < $b ? 6 : 0 ) ) / 6;
                break;
            case $g:
                $h = ( ( $b - $r ) / $diff + 2 ) / 6;
                break;
            case $b:
                $h = ( ( $r - $g ) / $diff + 4 ) / 6;
                break;
            default:
                $h = 0.0;
        }

        return array(
            'h' => $h,
            's' => $s,
            'l' => $l,
        );
    }

    public function hsl_to_hex( array $hsl ): string {
        $rgb = $this->hsl_to_rgb( $hsl['h'], $hsl['s'], $hsl['l'] );

        return sprintf( '#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b'] );
    }

    public function hsl_to_rgb( float $h, float $s, float $l ): array {
        if ( $s === 0.0 ) {
            $r = $g = $b = $l;
        } else {
            $hue_to_rgb = function ( float $p, float $q, float $t ): float {
                if ( $t < 0 ) {
                    $t += 1;
                }
                if ( $t > 1 ) {
                    $t -= 1;
                }
                if ( $t < 1 / 6 ) {
                    return $p + ( $q - $p ) * 6 * $t;
                }
                if ( $t < 1 / 2 ) {
                    return $q;
                }
                if ( $t < 2 / 3 ) {
                    return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
                }

                return $p;
            };

            $q = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
            $p = 2 * $l - $q;

            $r = $hue_to_rgb( $p, $q, $h + 1 / 3 );
            $g = $hue_to_rgb( $p, $q, $h );
            $b = $hue_to_rgb( $p, $q, $h - 1 / 3 );
        }

        return array(
            'r' => (int) round( $r * 255 ),
            'g' => (int) round( $g * 255 ),
            'b' => (int) round( $b * 255 ),
        );
    }
}
