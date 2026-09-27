<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use stdClass;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class SiteInfoTools {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/site-info-get',
            array(
                'label'               => __( 'Get Site Info', 'hostinger-ai-assistant' ),
                'description'         => __( 'Provides detailed information about the WordPress site like site name, url, description, admin email, plugins, themes, users, and more.', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => new stdClass(),
                ),
                'execute_callback'    => array( $this, 'get_site_info' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'tool',
                    ),
                    'annotations'  => array(
                        'title'         => 'Get Site Info',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    public function get_site_info( array $input ): array {
        return array(
            'site_name'        => get_bloginfo( 'name' ),
            'site_url'         => get_bloginfo( 'url' ),
            'site_description' => get_bloginfo( 'description' ),
            'site_admin_email' => get_bloginfo( 'admin_email' ),
            'plugins'          => $this->get_plugins_info(),
            'themes'           => array(
                'active' => $this->get_active_theme_info(),
                'all'    => wp_get_themes(),
            ),
            'users'            => $this->get_users_info(),
        );
    }

    private function get_plugins_info(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins_data     = array();
        $all_plugins      = get_plugins();
        $active_plugins   = get_option( 'active_plugins' );
        $inactive_plugins = array_diff( array_keys( $all_plugins ), $active_plugins );

        foreach ( $all_plugins as $plugin_path => $plugin_data ) {
            $plugin_slug = explode( '/', (string) $plugin_path )[0];
            $plugin_info = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path );
            $update_info = $this->get_plugin_update_info( $plugin_path );

            $plugins_data[] = array(
                'name'             => $plugin_info['Name'],
                'version'          => $plugin_info['Version'],
                'description'      => $plugin_info['Description'],
                'author'           => $plugin_info['Author'],
                'author_uri'       => $plugin_info['AuthorURI'],
                'plugin_uri'       => $plugin_info['PluginURI'],
                'text_domain'      => $plugin_info['TextDomain'],
                'domain_path'      => $plugin_info['DomainPath'],
                'network'          => $plugin_info['Network'],
                'requires_php'     => isset( $plugin_info['RequiresPHP'] ) ? $plugin_info['RequiresPHP'] : '',
                'requires_wp'      => isset( $plugin_info['RequiresWP'] ) ? $plugin_info['RequiresWP'] : '',
                'update_available' => $update_info['update_available'],
                'latest_version'   => $update_info['latest_version'],
                'last_updated'     => $update_info['last_updated'],
                'plugin_path'      => $plugin_path,
                'plugin_slug'      => $plugin_slug,
                'status'           => in_array( $plugin_path, $active_plugins, true ) ? 'active' : 'inactive',
            );
        }

        return array(
            'plugins'     => $plugins_data,
            'active'      => $active_plugins,
            'inactive'    => $inactive_plugins,
            'total_count' => count( $plugins_data ),
        );
    }

    private function get_plugin_update_info( string $plugin_path ): array {
        $update_info = array(
            'update_available' => false,
            'latest_version'   => '',
            'last_updated'     => '',
        );

        $update_plugins = get_site_transient( 'update_plugins' );

        if ( $update_plugins && isset( $update_plugins->response[ $plugin_path ] ) ) {
            $plugin_data                     = $update_plugins->response[ $plugin_path ];
            $update_info['update_available'] = true;
            $update_info['latest_version']   = $plugin_data->new_version;
            $update_info['last_updated']     = isset( $plugin_data->last_updated ) ? $plugin_data->last_updated : '';
        }

        return $update_info;
    }

    private function get_active_theme_info(): array {
        $active_theme = wp_get_theme();
        $parent_theme = $active_theme->parent() ? wp_get_theme( $active_theme->get_template() ) : null;
        $update_info  = $this->get_theme_update_info( $active_theme->get_stylesheet() );

        $theme_info = array(
            'active_theme' => array(
                'name'             => $active_theme->get( 'Name' ),
                'theme_uri'        => $active_theme->get( 'ThemeURI' ),
                'description'      => $active_theme->get( 'Description' ),
                'author'           => $active_theme->get( 'Author' ),
                'author_uri'       => $active_theme->get( 'AuthorURI' ),
                'version'          => $active_theme->get( 'Version' ),
                'license'          => $active_theme->get( 'License' ),
                'license_uri'      => $active_theme->get( 'LicenseURI' ),
                'text_domain'      => $active_theme->get( 'TextDomain' ),
                'domain_path'      => $active_theme->get( 'DomainPath' ),
                'requires_php'     => $active_theme->get( 'RequiresPHP' ),
                'requires_wp'      => $active_theme->get( 'RequiresWP' ),
                'status'           => $active_theme->get( 'Status' ),
                'tags'             => $active_theme->get( 'Tags' ),
                'template'         => $active_theme->get_template(),
                'stylesheet'       => $active_theme->get_stylesheet(),
                'screenshot'       => $active_theme->get_screenshot( 'relative' ),
                'update_available' => $update_info['update_available'],
                'latest_version'   => $update_info['latest_version'],
                'last_updated'     => $update_info['last_updated'],
            ),
        );

        if ( $parent_theme ) {
            $theme_info['parent_theme'] = array(
                'name'         => $parent_theme->get( 'Name' ),
                'theme_uri'    => $parent_theme->get( 'ThemeURI' ),
                'description'  => $parent_theme->get( 'Description' ),
                'author'       => $parent_theme->get( 'Author' ),
                'author_uri'   => $parent_theme->get( 'AuthorURI' ),
                'version'      => $parent_theme->get( 'Version' ),
                'license'      => $parent_theme->get( 'License' ),
                'license_uri'  => $parent_theme->get( 'LicenseURI' ),
                'text_domain'  => $parent_theme->get( 'TextDomain' ),
                'domain_path'  => $parent_theme->get( 'DomainPath' ),
                'requires_php' => $parent_theme->get( 'RequiresPHP' ),
                'requires_wp'  => $parent_theme->get( 'RequiresWP' ),
                'status'       => $parent_theme->get( 'Status' ),
                'tags'         => $parent_theme->get( 'Tags' ),
                'template'     => $parent_theme->get_template(),
                'stylesheet'   => $parent_theme->get_stylesheet(),
                'screenshot'   => $parent_theme->get_screenshot( 'relative' ),
            );
        }

        $theme_info['theme_supports'] = array(
            'post_thumbnails'      => current_theme_supports( 'post-thumbnails' ),
            'post_formats'         => current_theme_supports( 'post-formats' ),
            'custom_background'    => current_theme_supports( 'custom-background' ),
            'custom_header'        => current_theme_supports( 'custom-header' ),
            'custom_logo'          => current_theme_supports( 'custom-logo' ),
            'automatic_feed_links' => current_theme_supports( 'automatic-feed-links' ),
            'html5'                => array(
                'search-form',
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
                'style',
                'script',
            ),
        );

        $theme_info['theme_mods'] = get_theme_mods();

        return $theme_info;
    }

    private function get_theme_update_info( string $theme_slug ): array {
        $update_info = array(
            'update_available' => false,
            'latest_version'   => '',
            'last_updated'     => '',
        );

        $update_themes = get_site_transient( 'update_themes' );

        if ( $update_themes && isset( $update_themes->response ) ) {
            foreach ( $update_themes->response as $theme_name => $theme_data ) {
                if ( $theme_name === $theme_slug ) {
                    $update_info['update_available'] = true;
                    $update_info['latest_version']   = ! empty( $theme_data->new_version ) ? $theme_data->new_version : '';
                    $update_info['last_updated']     = isset( $theme_data->{'last-updated'} ) ? $theme_data->{'last-updated'} : '';
                    break;
                }
            }
        }

        return $update_info;
    }

    private function get_users_info(): array {
        $wp_roles  = wp_roles();
        $all_roles = $wp_roles->get_names();

        $role_stats = array();
        foreach ( $all_roles as $role_slug => $role_name ) {
            $role_users               = get_users( array( 'role' => $role_slug ) );
            $role_stats[ $role_slug ] = array(
                'name'  => $role_name,
                'count' => count( $role_users ),
            );
        }

        return array(
            'role_stats' => $role_stats,
        );
    }
}
