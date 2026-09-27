<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ThemeInfoResource {
    private const RESOURCE_URI = 'wordpress://theme-info';

    public function register(): void {
        wp_register_ability(
            'hostinger-ai/theme-info',
            array(
                'label'               => __( 'Theme Information', 'hostinger-ai-assistant' ),
                'description'         => __( 'Provides detailed information about the active WordPress theme and its parent theme if applicable', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'execute_callback'    => array( $this, 'get_theme_info' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'uri'          => self::RESOURCE_URI,
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'resource',
                    ),
                    'annotations'  => array(
                        'title'         => 'Theme Information',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    public function get_theme_info( array $input = array() ): array {
        $theme    = wp_get_theme();
        $is_child = $theme->parent() !== false;

        $theme_data = array(
            'uri'         => self::RESOURCE_URI,
            'text'        => '',
            'blob'        => '',
            'name'        => $theme->get( 'Name' ),
            'version'     => $theme->get( 'Version' ),
            'description' => $theme->get( 'Description' ),
            'author'      => $theme->get( 'Author' ),
            'theme_uri'   => $theme->get( 'ThemeURI' ),
            'template'    => $theme->get_template(),
            'stylesheet'  => $theme->get_stylesheet(),
            'is_child'    => $is_child,
        );

        if ( $is_child ) {
            $parent_theme         = $theme->parent();
            $theme_data['parent'] = array(
                'name'        => $parent_theme->get( 'Name' ),
                'version'     => $parent_theme->get( 'Version' ),
                'description' => $parent_theme->get( 'Description' ),
                'author'      => $parent_theme->get( 'Author' ),
                'theme_uri'   => $parent_theme->get( 'ThemeURI' ),
                'template'    => $parent_theme->get_template(),
                'stylesheet'  => $parent_theme->get_stylesheet(),
            );
        } else {
            $theme_data['parent'] = null;
        }

        return array( $theme_data );
    }
}
