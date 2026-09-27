<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class PluginsInfoResource {
    private const RESOURCE_URI = 'wordpress://plugins-info';

    public function register(): void {
        wp_register_ability(
            'hostinger-ai/plugins-info',
            array(
                'label'               => __( 'Plugins Information', 'hostinger-ai-assistant' ),
                'description'         => __( 'Provides detailed information about active and available WordPress plugins', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'execute_callback'    => array( $this, 'get_plugins_info' ),
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
                        'title'         => 'Plugins Information',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    public function get_plugins_info(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = get_plugins();
        $active_plugins = get_option( 'active_plugins', array() );

        $active_plugins_data = array();
        $all_plugins_data    = array();

        foreach ( $all_plugins as $plugin_path => $plugin_data ) {
            $is_active = in_array( $plugin_path, $active_plugins, true );

            $plugin_info = array(
                'name'        => $plugin_data['Name'],
                'version'     => $plugin_data['Version'],
                'description' => $plugin_data['Description'],
                'author'      => $plugin_data['Author'],
                'plugin_uri'  => $plugin_data['PluginURI'] ?? '',
                'is_active'   => $is_active,
                'path'        => $plugin_path,
            );

            $all_plugins_data[ $plugin_path ] = $plugin_info;

            if ( $is_active ) {
                $active_plugins_data[] = $plugin_info;
            }
        }

        return array(
            array(
                'uri'            => self::RESOURCE_URI,
                'text'           => '',
                'blob'           => '',
                'active_plugins' => $active_plugins_data,
                'all_plugins'    => $all_plugins_data,
            ),
        );
    }
}
