<?php

namespace Hostinger\AiAssistant\Mcp\Abilities;

use WP_Ability;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Single source of truth for which abilities this plugin's MCP server exposes.
 *
 * Both the server's component lists and the discovery meta-tools resolve scope through here, so
 * the two can never drift apart and start exposing different sets of abilities.
 */
class AbilityCatalog {
    public static function get_names_by_type( string $mcp_type ): array {
        $names = array();

        foreach ( self::get_by_type( $mcp_type ) as $ability ) {
            $names[] = $ability->get_name();
        }

        return $names;
    }

    /**
     * @return WP_Ability[]
     */
    public static function get_by_type( string $mcp_type ): array {
        $abilities = array();

        foreach ( wp_get_abilities() as $ability ) {
            if ( self::is_exposed( $ability, $mcp_type ) ) {
                $abilities[] = $ability;
            }
        }

        return $abilities;
    }

    public static function get_tool( string $name ): WP_Ability|WP_Error {
        if ( empty( $name ) ) {
            return new WP_Error(
                'missing_ability_name',
                __( 'A tool name is required.', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        $message = sprintf(
            /* translators: %s: requested tool name */
            __( 'No tool named "%s" is available on this server.', 'hostinger-ai-assistant' ),
            $name
        );

        if ( ! wp_has_ability( $name ) ) {
            return new WP_Error(
                'ability_not_found',
                $message,
                array( 'status' => 404 )
            );
        }

        $ability = wp_get_ability( $name );

        if ( ! $ability instanceof WP_Ability || ! self::is_exposed( $ability, 'tool' ) ) {
            return new WP_Error(
                'ability_not_found',
                $message,
                array( 'status' => 404 )
            );
        }

        return $ability;
    }

    private static function is_exposed( WP_Ability $ability, string $mcp_type ): bool {
        if ( $ability->get_category() !== AbilitiesRegistry::CATEGORY ) {
            return false;
        }

        $meta = $ability->get_meta();

        if ( empty( $meta['mcp']['public'] ) ) {
            return false;
        }

        return ( $meta['mcp']['type'] ?? '' ) === $mcp_type;
    }
}
