<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class SiteInfoResource {
    private const RESOURCE_URI = 'wordpress://site-info';

    public function register(): void {
        wp_register_ability(
            'hostinger-ai/site-info',
            array(
                'label'               => __( 'Site Information', 'hostinger-ai-assistant' ),
                'description'         => __( 'Provides general information about the WordPress site including site details, plugins, themes, and users', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'execute_callback'    => array( $this, 'get_site_info' ),
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
                        'title'         => 'Site Information',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    public function get_site_info( array $input = array() ): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $active_theme      = wp_get_theme();
        $active_theme_data = array(
            'name'        => $active_theme->get( 'Name' ),
            'version'     => $active_theme->get( 'Version' ),
            'description' => $active_theme->get( 'Description' ),
            'author'      => $active_theme->get( 'Author' ),
            'theme_uri'   => $active_theme->get( 'ThemeURI' ),
            'template'    => $active_theme->get_template(),
            'stylesheet'  => $active_theme->get_stylesheet(),
        );

        $all_themes      = wp_get_themes();
        $all_themes_data = array();
        foreach ( $all_themes as $theme_slug => $theme ) {
            $all_themes_data[ $theme_slug ] = array(
                'name'    => $theme->get( 'Name' ),
                'version' => $theme->get( 'Version' ),
                'author'  => $theme->get( 'Author' ),
            );
        }

        $post_types       = get_post_types( array( 'public' => true ), 'names' );
        $posts_count_data = array();
        foreach ( $post_types as $post_type ) {
            $post_count = wp_count_posts( $post_type );

            $total_count = 0;
            foreach ( $post_count as $status => $count ) {
                if ( $status !== 'auto-draft' ) {
                    $total_count += $count;
                }
            }
            $posts_count_data[ $post_type ] = $total_count;
        }

        return array(
            array(
                'uri'               => self::RESOURCE_URI,
                'text'              => '',
                'blob'              => '',
                'site_name'         => get_bloginfo( 'name' ),
                'site_url'          => get_bloginfo( 'url' ),
                'site_description'  => get_bloginfo( 'description' ),
                'site_admin_email'  => get_bloginfo( 'admin_email' ),
                'wordpress_version' => get_bloginfo( 'version' ),
                'language'          => get_bloginfo( 'language' ),
                'timezone'          => wp_timezone_string(),
                'active_plugins'    => get_option( 'active_plugins' ),
                'all_plugins'       => get_plugins(),
                'all_themes'        => $all_themes_data,
                'active_theme'      => $active_theme_data,
                'users_count'       => array(
                    'total' => count_users()['total_users'],
                    'roles' => count_users()['avail_roles'],
                ),
                'posts_count'       => $posts_count_data,
            ),
        );
    }
}
