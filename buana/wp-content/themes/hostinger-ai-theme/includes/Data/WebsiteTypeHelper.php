<?php

namespace Hostinger\AiTheme\Data;

defined( 'ABSPATH' ) || exit;

class WebsiteTypeHelper {
    public static function get_website_types(): array {
        $value = get_option( 'hostinger_ai_website_type', [] );

        // Backward compatibility: if it's a plain string (old format), wrap in array.
        if ( is_string( $value ) ) {
            return ! empty( $value ) ? [ $value ] : [];
        }

        return is_array( $value ) ? $value : [];
    }

    public static function normalize( string $type ): string {
        return strtolower( str_replace( '-', ' ', trim( $type ) ) );
    }

    public static function has_website_type( string $type ): bool {
        $normalized = self::normalize( $type );
        foreach ( self::get_website_types() as $stored ) {
            if ( is_string( $stored ) && self::normalize( $stored ) === $normalized ) {
                return true;
            }
        }
        return false;
    }

    public static function is_only_website_type( string $type ): bool {
        $types = self::get_website_types();
        return count( $types ) === 1 && is_string( $types[0] ) && self::normalize( $types[0] ) === self::normalize( $type );
    }

    public static function contains( array $types, string $type ): bool {
        $normalized = self::normalize( $type );
        foreach ( $types as $stored ) {
            if ( is_string( $stored ) && self::normalize( $stored ) === $normalized ) {
                return true;
            }
        }
        return false;
    }
}
