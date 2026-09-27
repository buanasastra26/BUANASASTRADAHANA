<?php

namespace Hostinger\AiTheme\Builder;

use GdImage;

defined( 'ABSPATH' ) || exit;

/**
 * Repaints a transparent raster logo onto a new ink and accent colour.
 */
class LogoRecolor {
    use ColorUtils;

    private const MAX_SIDE    = 512;
    private const SAMPLE_SIDE = 256;

    private const MIN_ROLE_SHARE     = 0.03;
    private const MERGE_DISTANCE     = 48.0;
    private const LIGHTNESS_WEIGHT   = 0.5;
    private const ACHROMATIC_CHROMA  = 16.0;
    private const HUE_SATURATION     = 0.25;
    private const HUE_TOLERANCE_DEG  = 30.0;
    private const COMPONENT_MAJORITY = 0.8;
    private const PALETTE_HUES       = 2;

    private const MIN_TRANSPARENT_SHARE = 0.05;

    private const SHADE_BODY_WIDTH   = 8;
    private const SHADE_FLAT_SHARE   = 0.6;
    private const SHADE_MIN_RANGE    = 20.0;
    private const SHADE_MIN_SPAN     = 70.0;
    private const SHADE_MAX_SPAN     = 120.0;
    private const SHADE_MIN_CONTRAST = 3.0;

    private const SPLIT_BINS      = 32;
    private const SPLIT_MIN_SHARE = 0.10;
    private const SPLIT_MIN_GAP   = 24.0;
    private const SPLIT_VALLEY    = 0.25;

    private const ALPHA_TRANSPARENT = 127;
    private const ALPHA_OPAQUE_MAX  = 63;

    public static function is_supported(): bool {
        return function_exists( 'imagecreatefromstring' ) && function_exists( 'imagepng' );
    }

    public function analyze( string $source_path ): ?array {
        $image = $this->load( $source_path );

        if ( $image === null ) {
            return null;
        }

        return $this->analyze_image( $image );
    }

    public function recolor( string $source_path, string $ink_hex, string $accent_hex, ?string $background_hex = null ): ?string {
        if ( ! $this->is_valid_hex_color( $ink_hex ) || ! $this->is_valid_hex_color( $accent_hex ) ) {
            return null;
        }

        if ( $background_hex !== null && ! $this->is_valid_hex_color( $background_hex ) ) {
            return null;
        }

        $image = $this->load( $source_path );

        if ( $image === null ) {
            return null;
        }

        $analysis = $this->analyze_image( $image );

        if ( $analysis === null || $analysis['transparent_share'] < self::MIN_TRANSPARENT_SHARE ) {
            return null;
        }

        $this->paint( $image, $analysis['roles'], $this->role_targets( $analysis, $ink_hex, $accent_hex ), $background_hex );

        ob_start();
        imagepng( $image, null, 6 );
        $png = ob_get_clean();

        return is_string( $png ) && $png !== '' ? $png : null;
    }

    private function load( string $source_path ): ?GdImage {
        if ( ! self::is_supported() || ! is_readable( $source_path ) ) {
            return null;
        }

        $bytes = file_get_contents( $source_path );

        if ( $bytes === false || $bytes === '' ) {
            return null;
        }

        $image = @imagecreatefromstring( $bytes );

        if ( ! $image instanceof GdImage ) {
            return null;
        }

        if ( ! imageistruecolor( $image ) ) {
            imagepalettetotruecolor( $image );
        }

        $width   = imagesx( $image );
        $height  = imagesy( $image );
        $longest = max( $width, $height );

        if ( $longest > self::MAX_SIDE ) {
            $scale  = self::MAX_SIDE / $longest;
            $scaled = $this->transparent_canvas( (int) round( $width * $scale ), (int) round( $height * $scale ) );
            imagecopyresampled( $scaled, $image, 0, 0, 0, 0, imagesx( $scaled ), imagesy( $scaled ), $width, $height );
            $image = $scaled;
        }

        imagealphablending( $image, false );
        imagesavealpha( $image, true );

        return $image;
    }

    private function transparent_canvas( int $width, int $height ): GdImage {
        $canvas = imagecreatetruecolor( max( 1, $width ), max( 1, $height ) );
        imagealphablending( $canvas, false );
        imagesavealpha( $canvas, true );
        imagefill( $canvas, 0, 0, imagecolorallocatealpha( $canvas, 0, 0, 0, self::ALPHA_TRANSPARENT ) );

        return $canvas;
    }

    private function sample( GdImage $image ): GdImage {
        $width   = imagesx( $image );
        $height  = imagesy( $image );
        $longest = max( $width, $height );

        if ( $longest <= self::SAMPLE_SIDE ) {
            return $image;
        }

        $scale  = self::SAMPLE_SIDE / $longest;
        $sample = $this->transparent_canvas( (int) round( $width * $scale ), (int) round( $height * $scale ) );
        imagecopyresized( $sample, $image, 0, 0, 0, 0, imagesx( $sample ), imagesy( $sample ), $width, $height );

        return $sample;
    }

    private function analyze_image( GdImage $image ): ?array {
        $sample = $this->sample( $image );
        $width  = imagesx( $sample );
        $height = imagesy( $sample );

        $buckets     = array();
        $opaque      = 0;
        $transparent = 0;

        for ( $y = 0; $y < $height; $y++ ) {
            for ( $x = 0; $x < $width; $x++ ) {
                $color = imagecolorat( $sample, $x, $y );
                $alpha = ( $color >> 24 ) & 0x7F;

                if ( $alpha === self::ALPHA_TRANSPARENT ) {
                    ++$transparent;
                    continue;
                }

                if ( $alpha > self::ALPHA_OPAQUE_MAX ) {
                    continue;
                }

                $r   = ( $color >> 16 ) & 0xFF;
                $g   = ( $color >> 8 ) & 0xFF;
                $b   = $color & 0xFF;
                $key = ( ( $r >> 3 ) << 10 ) | ( ( $g >> 3 ) << 5 ) | ( $b >> 3 );

                if ( ! isset( $buckets[ $key ] ) ) {
                    $buckets[ $key ] = array( 0, 0, 0, 0 );
                }

                ++$buckets[ $key ][0];
                $buckets[ $key ][1] += $r;
                $buckets[ $key ][2] += $g;
                $buckets[ $key ][3] += $b;
                ++$opaque;
            }
        }

        if ( $opaque === 0 ) {
            return null;
        }

        $buckets = array_values( $buckets );
        $roles   = $this->merge_buckets( $buckets, $opaque );
        $hues    = $this->distinct_hues( $roles );

        if ( $hues <= 1 && count( $roles ) <= 2 ) {
            $roles = $this->split_by_lightness( $buckets, $roles, $opaque );
        }

        return array(
            'roles'             => $roles,
            'distinct_hues'     => $hues,
            'transparent_share' => $transparent / ( $width * $height ),
        );
    }

    private function split_by_lightness( array $buckets, array $roles, int $opaque ): array {
        $centroids = array_column( $roles, 'rgb' );
        $bin_width = 256 / self::SPLIT_BINS;
        $histogram = array_fill( 0, self::SPLIT_BINS, 0 );
        $members   = array();

        foreach ( $buckets as $bucket ) {
            $rgb = array( $bucket[1] / $bucket[0], $bucket[2] / $bucket[0], $bucket[3] / $bucket[0] );

            if ( $this->nearest_centroid( $rgb, $centroids ) !== 0 ) {
                continue;
            }

            $bin                = min( self::SPLIT_BINS - 1, (int) ( $this->luma( ...$rgb ) / $bin_width ) );
            $histogram[ $bin ] += $bucket[0];
            $members[]          = $bucket;
        }

        $total = array_sum( $histogram );

        if ( $total === 0 ) {
            return $roles;
        }

        $threshold = $this->otsu_threshold( $histogram );

        if ( $threshold === null ) {
            return $roles;
        }

        $below = array_sum( array_slice( $histogram, 0, $threshold ) );
        $above = $total - $below;

        if ( $below < $total * self::SPLIT_MIN_SHARE || $above < $total * self::SPLIT_MIN_SHARE ) {
            return $roles;
        }

        $valley = min( $histogram[ $threshold - 1 ], $histogram[ $threshold ] );
        $peak   = min( max( array_slice( $histogram, 0, $threshold ) ), max( array_slice( $histogram, $threshold ) ) );

        if ( $valley > self::SPLIT_VALLEY * $peak ) {
            return $roles;
        }

        $cut    = $threshold * $bin_width;
        $groups = array( array( 0, 0, 0, 0 ), array( 0, 0, 0, 0 ) );

        foreach ( $members as $bucket ) {
            $luma  = $this->luma( $bucket[1] / $bucket[0], $bucket[2] / $bucket[0], $bucket[3] / $bucket[0] );
            $index = $luma < $cut ? 0 : 1;

            for ( $i = 0; $i < 4; $i++ ) {
                $groups[ $index ][ $i ] += $bucket[ $i ];
            }
        }

        $means = array_map(
            fn( array $group ): float => $this->luma( $group[1] / $group[0], $group[2] / $group[0], $group[3] / $group[0] ),
            $groups
        );

        if ( abs( $means[0] - $means[1] ) < self::SPLIT_MIN_GAP ) {
            return $roles;
        }

        $split = array_map(
            static fn( array $group ): array => array(
                'rgb'   => array(
                    (int) round( $group[1] / $group[0] ),
                    (int) round( $group[2] / $group[0] ),
                    (int) round( $group[3] / $group[0] ),
                ),
                'share' => $group[0] / $opaque,
            ),
            $groups
        );

        $roles = array_merge( $split, array_slice( $roles, 1 ) );

        usort( $roles, static fn( array $a, array $b ): int => $b['share'] <=> $a['share'] );

        return $roles;
    }

    private function otsu_threshold( array $histogram ): ?int {
        $bins  = count( $histogram );
        $total = array_sum( $histogram );
        $sum   = 0.0;

        foreach ( $histogram as $bin => $count ) {
            $sum += $bin * $count;
        }

        $best      = -1.0;
        $threshold = null;
        $weight    = 0;
        $moment    = 0.0;

        for ( $bin = 1; $bin < $bins; $bin++ ) {
            $weight += $histogram[ $bin - 1 ];
            $moment += ( $bin - 1 ) * $histogram[ $bin - 1 ];
            $rest    = $total - $weight;

            if ( $weight === 0 || $rest === 0 ) {
                continue;
            }

            $variance = $weight * $rest * ( $moment / $weight - ( $sum - $moment ) / $rest ) ** 2;

            if ( $variance > $best ) {
                $best      = $variance;
                $threshold = $bin;
            }
        }

        return $threshold;
    }

    private function merge_buckets( array $buckets, int $opaque ): array {
        usort( $buckets, static fn( array $a, array $b ): int => $b[0] <=> $a[0] );

        $merged = array();

        foreach ( $buckets as $bucket ) {
            $centroid = array( $bucket[1] / $bucket[0], $bucket[2] / $bucket[0], $bucket[3] / $bucket[0] );

            foreach ( $merged as $index => $role ) {
                $existing = array( $role[1] / $role[0], $role[2] / $role[0], $role[3] / $role[0] );

                if ( $this->same_role( $existing, $centroid ) ) {
                    $merged[ $index ][0] += $bucket[0];
                    $merged[ $index ][1] += $bucket[1];
                    $merged[ $index ][2] += $bucket[2];
                    $merged[ $index ][3] += $bucket[3];
                    continue 2;
                }
            }

            $merged[] = $bucket;
        }

        $merged = $this->merge_roles( $merged );

        $centroids = array_map(
            static fn( array $role ): array => array( $role[1] / $role[0], $role[2] / $role[0], $role[3] / $role[0] ),
            $merged
        );
        $assigned  = array_fill( 0, count( $centroids ), array( 0, 0, 0, 0 ) );

        foreach ( $buckets as $bucket ) {
            $index = $this->nearest_centroid(
                array( $bucket[1] / $bucket[0], $bucket[2] / $bucket[0], $bucket[3] / $bucket[0] ),
                $centroids
            );

            $assigned[ $index ][0] += $bucket[0];
            $assigned[ $index ][1] += $bucket[1];
            $assigned[ $index ][2] += $bucket[2];
            $assigned[ $index ][3] += $bucket[3];
        }

        $roles = array();

        foreach ( $assigned as $role ) {
            if ( $role[0] === 0 || $role[0] / $opaque < self::MIN_ROLE_SHARE ) {
                continue;
            }

            $roles[] = array(
                'rgb'   => array(
                    (int) round( $role[1] / $role[0] ),
                    (int) round( $role[2] / $role[0] ),
                    (int) round( $role[3] / $role[0] ),
                ),
                'share' => $role[0] / $opaque,
            );
        }

        usort( $roles, static fn( array $a, array $b ): int => $b['share'] <=> $a['share'] );

        return $roles;
    }

    private function merge_roles( array $roles ): array {
        do {
            $changed = false;

            foreach ( $roles as $i => $a ) {
                foreach ( $roles as $j => $b ) {
                    if ( $j <= $i ) {
                        continue;
                    }

                    $centroid_a = array( $a[1] / $a[0], $a[2] / $a[0], $a[3] / $a[0] );
                    $centroid_b = array( $b[1] / $b[0], $b[2] / $b[0], $b[3] / $b[0] );

                    if ( ! $this->same_role( $centroid_a, $centroid_b ) ) {
                        continue;
                    }

                    $roles[ $i ] = array( $a[0] + $b[0], $a[1] + $b[1], $a[2] + $b[2], $a[3] + $b[3] );
                    unset( $roles[ $j ] );
                    $roles   = array_values( $roles );
                    $changed = true;
                    break 2;
                }
            }
        } while ( $changed );

        return $roles;
    }

    private function same_role( array $a, array $b ): bool {
        $chroma_a = $this->chroma( $a );
        $chroma_b = $this->chroma( $b );

        if ( $this->magnitude( $chroma_a ) < self::ACHROMATIC_CHROMA && $this->magnitude( $chroma_b ) < self::ACHROMATIC_CHROMA ) {
            return true;
        }

        $delta_l = ( $this->luma( ...$a ) - $this->luma( ...$b ) ) * self::LIGHTNESS_WEIGHT;

        $distance = sqrt(
            ( $chroma_a[0] - $chroma_b[0] ) ** 2
            + ( $chroma_a[1] - $chroma_b[1] ) ** 2
            + ( $chroma_a[2] - $chroma_b[2] ) ** 2
            + $delta_l ** 2
        );

        return $distance < self::MERGE_DISTANCE;
    }

    private function chroma( array $rgb ): array {
        $luma = $this->luma( ...$rgb );

        return array( $rgb[0] - $luma, $rgb[1] - $luma, $rgb[2] - $luma );
    }

    private function magnitude( array $vector ): float {
        return sqrt( $vector[0] ** 2 + $vector[1] ** 2 + $vector[2] ** 2 );
    }

    private function distinct_hues( array $roles ): int {
        $hues = array();

        foreach ( $roles as $role ) {
            if ( $this->saturation( $role['rgb'] ) < self::HUE_SATURATION ) {
                continue;
            }

            $hue      = $this->hue_degrees( $role['rgb'] );
            $distinct = true;

            foreach ( $hues as $known ) {
                $delta = abs( $hue - $known );

                if ( min( $delta, 360 - $delta ) <= self::HUE_TOLERANCE_DEG ) {
                    $distinct = false;
                    break;
                }
            }

            if ( $distinct ) {
                $hues[] = $hue;
            }
        }

        return count( $hues );
    }

    private function saturation( array $rgb ): float {
        $high = max( $rgb );

        return $high <= 0 ? 0.0 : ( $high - min( $rgb ) ) / $high;
    }

    private function hue_degrees( array $rgb ): float {
        $r    = $rgb[0] / 255;
        $g    = $rgb[1] / 255;
        $b    = $rgb[2] / 255;
        $high = max( $r, $g, $b );
        $low  = min( $r, $g, $b );

        if ( $high === $low ) {
            return 0.0;
        }

        if ( $high === $r ) {
            $hue = fmod( ( $g - $b ) / ( $high - $low ), 6 );
            $hue = $hue < 0 ? $hue + 6 : $hue;
        } elseif ( $high === $g ) {
            $hue = ( $b - $r ) / ( $high - $low ) + 2;
        } else {
            $hue = ( $r - $g ) / ( $high - $low ) + 4;
        }

        return $hue * 60;
    }

    private function nearest_centroid( array $rgb, array $centroids ): int {
        $best          = 0;
        $best_distance = PHP_FLOAT_MAX;

        foreach ( $centroids as $index => $centroid ) {
            $distance = ( $rgb[0] - $centroid[0] ) ** 2 + ( $rgb[1] - $centroid[1] ) ** 2 + ( $rgb[2] - $centroid[2] ) ** 2;

            if ( $distance < $best_distance ) {
                $best_distance = $distance;
                $best          = $index;
            }
        }

        return $best;
    }

    private function role_targets( array $analysis, string $ink_hex, string $accent_hex ): array {
        $ink      = array_values( $this->hex_to_rgb( $ink_hex ) );
        $accent   = array_values( $this->hex_to_rgb( $accent_hex ) );
        $ink_only = $analysis['distinct_hues'] > self::PALETTE_HUES;
        $targets  = array();

        foreach ( array_keys( $analysis['roles'] ) as $index ) {
            if ( $index === 0 ) {
                $targets[] = $ink;
            } elseif ( $ink_only ) {
                $targets[] = null;
            } elseif ( $index === 1 ) {
                $targets[] = $accent;
            } else {
                $factor    = min( 0.8, 0.35 * ( $index - 1 ) );
                $targets[] = array(
                    (int) round( $accent[0] + ( $ink[0] - $accent[0] ) * $factor ),
                    (int) round( $accent[1] + ( $ink[1] - $accent[1] ) * $factor ),
                    (int) round( $accent[2] + ( $ink[2] - $accent[2] ) * $factor ),
                );
            }
        }

        return $targets;
    }

    private function shade_mapping( array $histogram, array $target, ?string $background_hex ): ?array {
        $total = array_sum( $histogram );

        if ( $total === 0 ) {
            return null;
        }

        $mode = (int) array_search( max( $histogram ), $histogram, true );
        $body = array_sum( array_slice( $histogram, max( 0, $mode - self::SHADE_BODY_WIDTH ), 2 * self::SHADE_BODY_WIDTH + 1 ) );

        if ( $body > self::SHADE_FLAT_SHARE * $total ) {
            return null;
        }

        $low  = $this->percentile( $histogram, $total, 0.02 );
        $high = $this->percentile( $histogram, $total, 0.98 );

        if ( $high - $low < self::SHADE_MIN_RANGE ) {
            return null;
        }

        $span        = min( max( $high - $low, self::SHADE_MIN_SPAN ), self::SHADE_MAX_SPAN );
        $target_luma = $this->luma( ...$target );
        $up          = $target_luma < 128;

        if ( $background_hex !== null ) {
            $background_light = $this->get_luminance( $background_hex ) > 0.5;
            $up               = ! $background_light;

            if ( ( $up ? 255 - $target_luma : $target_luma ) < $span ) {
                $up = 255 - $target_luma > $target_luma;
            }

            for ( $attempt = 0; $attempt < 20; $attempt++ ) {
                $far = sprintf(
                    '#%02x%02x%02x',
                    $this->clamp( $target[0] + ( $up ? $span : -$span ) ),
                    $this->clamp( $target[1] + ( $up ? $span : -$span ) ),
                    $this->clamp( $target[2] + ( $up ? $span : -$span ) )
                );

                if ( $this->calculate_contrast_ratio( $far, $background_hex ) >= self::SHADE_MIN_CONTRAST ) {
                    break;
                }

                $span *= 0.85;
            }
        }

        return array(
            'up'    => $up,
            'low'   => $low,
            'high'  => $high,
            'scale' => $span / ( $high - $low ),
        );
    }

    private function percentile( array $histogram, int $total, float $fraction ): float {
        $needed = $total * $fraction;
        $seen   = 0;

        foreach ( $histogram as $value => $count ) {
            $seen += $count;

            if ( $seen >= $needed ) {
                return (float) $value;
            }
        }

        return 255.0;
    }

    private function paint( GdImage $image, array $roles, array $targets, ?string $background_hex ): void {
        $width      = imagesx( $image );
        $height     = imagesy( $image );
        $count      = $width * $height;
        $centroids  = array_column( $roles, 'rgb' );
        $role_count = count( $centroids );

        if ( $role_count === 0 ) {
            return;
        }

        $labels    = array_fill( 0, $count, -1 );
        $blob_role = array();
        $cache     = array();

        for ( $start = 0; $start < $count; $start++ ) {
            if ( $labels[ $start ] !== -1 ) {
                continue;
            }

            $color = imagecolorat( $image, $start % $width, intdiv( $start, $width ) );

            if ( ( ( $color >> 24 ) & 0x7F ) === self::ALPHA_TRANSPARENT ) {
                $labels[ $start ] = -2;
                continue;
            }

            $blob             = count( $blob_role );
            $votes            = array_fill( 0, $role_count, 0 );
            $stack            = array( $start );
            $colors           = array( $color );
            $labels[ $start ] = $blob;

            while ( $stack ) {
                $pixel = array_pop( $stack );
                $color = array_pop( $colors );

                if ( ( ( $color >> 24 ) & 0x7F ) <= self::ALPHA_OPAQUE_MAX ) {
                    ++$votes[ $this->nearest_role( $color & 0xFFFFFF, $centroids, $cache ) ];
                }

                $x = $pixel % $width;
                $y = intdiv( $pixel, $width );

                $neighbours = array();
                if ( $x > 0 ) {
                    $neighbours[] = $pixel - 1;
                }
                if ( $x < $width - 1 ) {
                    $neighbours[] = $pixel + 1;
                }
                if ( $y > 0 ) {
                    $neighbours[] = $pixel - $width;
                }
                if ( $y < $height - 1 ) {
                    $neighbours[] = $pixel + $width;
                }

                foreach ( $neighbours as $next ) {
                    if ( $labels[ $next ] !== -1 ) {
                        continue;
                    }

                    $next_color = imagecolorat( $image, $next % $width, intdiv( $next, $width ) );

                    if ( ( ( $next_color >> 24 ) & 0x7F ) === self::ALPHA_TRANSPARENT ) {
                        $labels[ $next ] = -2;
                        continue;
                    }

                    $labels[ $next ] = $blob;
                    $stack[]         = $next;
                    $colors[]        = $next_color;
                }
            }

            $total  = array_sum( $votes );
            $winner = -1;

            if ( $total > 0 ) {
                $max = max( $votes );

                if ( $max >= self::COMPONENT_MAJORITY * $total ) {
                    $winner = (int) array_search( $max, $votes, true );
                }
            }

            $blob_role[ $blob ] = $winner;
        }

        $ink_histogram = array_fill( 0, 256, 0 );

        for ( $pixel = 0; $pixel < $count; $pixel++ ) {
            $blob = $labels[ $pixel ];

            if ( $blob < 0 ) {
                continue;
            }

            $color = imagecolorat( $image, $pixel % $width, intdiv( $pixel, $width ) );
            $rgb   = $color & 0xFFFFFF;
            $role  = $blob_role[ $blob ];

            if ( $role < 0 ) {
                $role = $this->nearest_role( $rgb, $centroids, $cache );
            }

            $labels[ $pixel ] = $role;

            if ( $role === 0 && ( ( $color >> 24 ) & 0x7F ) <= self::ALPHA_OPAQUE_MAX ) {
                ++$ink_histogram[ (int) $this->luma( ( $rgb >> 16 ) & 0xFF, ( $rgb >> 8 ) & 0xFF, $rgb & 0xFF ) ];
            }
        }

        $shade = $targets[0] === null ? null : $this->shade_mapping( $ink_histogram, $targets[0], $background_hex );

        for ( $pixel = 0; $pixel < $count; $pixel++ ) {
            $role = $labels[ $pixel ];

            if ( $role < 0 ) {
                continue;
            }

            $target = $targets[ $role ] ?? null;

            if ( $target === null ) {
                continue;
            }

            $x     = $pixel % $width;
            $y     = intdiv( $pixel, $width );
            $color = imagecolorat( $image, $x, $y );
            $luma  = $this->luma( ( $color >> 16 ) & 0xFF, ( $color >> 8 ) & 0xFF, $color & 0xFF );

            if ( $role === 0 && $shade !== null ) {
                $offset = $shade['up']
                    ? ( $luma - $shade['low'] ) * $shade['scale']
                    : ( $luma - $shade['high'] ) * $shade['scale'];
            } else {
                $offset = $luma - $this->luma( ...$centroids[ $role ] );
            }

            $painted = ( $color & 0x7F000000 )
                | ( $this->clamp( $target[0] + $offset ) << 16 )
                | ( $this->clamp( $target[1] + $offset ) << 8 )
                | $this->clamp( $target[2] + $offset );

            imagesetpixel( $image, $x, $y, $painted );
        }
    }

    private function nearest_role( int $rgb, array $centroids, array &$cache ): int {
        if ( isset( $cache[ $rgb ] ) ) {
            return $cache[ $rgb ];
        }

        $role = $this->nearest_centroid(
            array( ( $rgb >> 16 ) & 0xFF, ( $rgb >> 8 ) & 0xFF, $rgb & 0xFF ),
            $centroids
        );

        $cache[ $rgb ] = $role;

        return $role;
    }

    private function luma( float $r, float $g, float $b ): float {
        return $r * 0.299 + $g * 0.587 + $b * 0.114;
    }

    private function clamp( float $value ): int {
        return (int) max( 0, min( 255, round( $value ) ) );
    }
}
