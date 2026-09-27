<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_Error;
use WP_Ajax_Upgrader_Skin;
use Plugin_Upgrader;
use stdClass;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class PluginTools {
    protected string $type = 'tool';

    public function register(): void {
        $this->register_list_operation();
        $this->register_install_operation();
        $this->register_delete_operation();
        $this->register_activate_operation();
        $this->register_deactivate_operation();
        $this->register_update_operation();
    }

    private function register_list_operation(): void {
        $ability_args = array(
            'label'               => __( 'List Plugins', 'hostinger-ai-assistant' ),
            'description'         => __( 'List all installed plugins with their exact plugin file path, name, version, and active status. Use this FIRST to check whether a plugin is already installed before installing or activating it, and to get the exact "plugin_file" value required by the activate, deactivate, update, and delete tools.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => new stdClass(),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'plugins' => array(
                        'type'  => 'array',
                        'items' => array(
                            'type'       => 'object',
                            'properties' => array(
                                'slug'        => array( 'type' => 'string' ),
                                'plugin_file' => array( 'type' => 'string' ),
                                'name'        => array( 'type' => 'string' ),
                                'version'     => array( 'type' => 'string' ),
                                'active'      => array( 'type' => 'boolean' ),
                            ),
                        ),
                    ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_list' ),
            'permission_callback' => function () {
                return current_user_can( 'activate_plugins' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'         => 'List Plugins',
                    'readOnlyHint'  => true,
                    'openWorldHint' => true,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/plugin-list', $ability_args );
    }

    private function register_install_operation(): void {
        $ability_args = array(
            'label'               => __( 'Install Plugin', 'hostinger-ai-assistant' ),
            'description'         => __( 'Install a plugin from the WordPress.org repository by its slug. To add a plugin to the site, ALWAYS call this first: it downloads and installs the plugin but does NOT activate it. If the plugin is already installed it returns a "plugin_already_installed" error instead of failing destructively, so it is safe to call when unsure. On success it returns the "plugin_file" path; pass that to the Activate Plugin tool to enable it. Never call Activate Plugin before a plugin has been installed.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'required'   => array( 'slug' ),
                'properties' => array(
                    'slug' => array(
                        'type'        => 'string',
                        'description' => __( "WordPress.org plugin slug (e.g. 'woocommerce')", 'hostinger-ai-assistant' ),
                    ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success'     => array( 'type' => 'boolean' ),
                    'plugin_file' => array( 'type' => 'string' ),
                    'name'        => array( 'type' => 'string' ),
                    'version'     => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_install' ),
            'permission_callback' => function () {
                return current_user_can( 'install_plugins' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'           => 'Install Plugin',
                    'readOnlyHint'    => false,
                    'destructiveHint' => false,
                    'idempotentHint'  => false,
                    'openWorldHint'   => true,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/plugin-install', $ability_args );
    }

    private function register_delete_operation(): void {
        $ability_args = array(
            'label'               => __( 'Delete Plugin', 'hostinger-ai-assistant' ),
            'description'         => __( 'Delete an installed plugin by plugin file path. Cannot delete an active plugin.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'required'   => array( 'plugin_file' ),
                'properties' => array(
                    'plugin_file' => array(
                        'type'        => 'string',
                        'description' => __( "Plugin file path relative to the plugins directory (e.g. 'woocommerce/woocommerce.php')", 'hostinger-ai-assistant' ),
                    ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success'     => array( 'type' => 'boolean' ),
                    'plugin_file' => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_delete' ),
            'permission_callback' => function () {
                return current_user_can( 'delete_plugins' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'           => 'Delete Plugin',
                    'readOnlyHint'    => false,
                    'destructiveHint' => true,
                    'idempotentHint'  => false,
                    'openWorldHint'   => true,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/plugin-delete', $ability_args );
    }

    private function register_activate_operation(): void {
        $ability_args = array(
            'label'               => __( 'Activate Plugin', 'hostinger-ai-assistant' ),
            'description'         => __( 'Activate a plugin that is ALREADY installed, using its exact plugin file path. Do not use this to add a new plugin: a plugin must be installed first. Before activating, confirm the plugin is installed with the List Plugins tool (or install it with the Install Plugin tool) and use the exact "plugin_file" it returns; a guessed path returns a "plugin_not_found" error. Returns success if the plugin is already active.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'required'   => array( 'plugin_file' ),
                'properties' => array(
                    'plugin_file' => array(
                        'type'        => 'string',
                        'description' => __( 'Plugin file path relative to the plugins directory to activate', 'hostinger-ai-assistant' ),
                    ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success'     => array( 'type' => 'boolean' ),
                    'plugin_file' => array( 'type' => 'string' ),
                    'name'        => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_activate' ),
            'permission_callback' => function () {
                return current_user_can( 'activate_plugins' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'           => 'Activate Plugin',
                    'readOnlyHint'    => false,
                    'destructiveHint' => false,
                    'idempotentHint'  => true,
                    'openWorldHint'   => true,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/plugin-activate', $ability_args );
    }

    private function register_deactivate_operation(): void {
        $ability_args = array(
            'label'               => __( 'Deactivate Plugin', 'hostinger-ai-assistant' ),
            'description'         => __( 'Deactivate an active plugin by plugin file path.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'required'   => array( 'plugin_file' ),
                'properties' => array(
                    'plugin_file' => array(
                        'type'        => 'string',
                        'description' => __( 'Plugin file path relative to the plugins directory to deactivate', 'hostinger-ai-assistant' ),
                    ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success'     => array( 'type' => 'boolean' ),
                    'plugin_file' => array( 'type' => 'string' ),
                    'name'        => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_deactivate' ),
            'permission_callback' => function () {
                return current_user_can( 'activate_plugins' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'           => 'Deactivate Plugin',
                    'readOnlyHint'    => false,
                    'destructiveHint' => false,
                    'idempotentHint'  => true,
                    'openWorldHint'   => true,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/plugin-deactivate', $ability_args );
    }

    private function register_update_operation(): void {
        $ability_args = array(
            'label'               => __( 'Update Plugin', 'hostinger-ai-assistant' ),
            'description'         => __( 'Update an installed plugin to the latest version from WordPress.org.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'required'   => array( 'plugin_file' ),
                'properties' => array(
                    'plugin_file' => array(
                        'type'        => 'string',
                        'description' => __( 'Plugin file path relative to the plugins directory to update', 'hostinger-ai-assistant' ),
                    ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success'      => array( 'type' => 'boolean' ),
                    'plugin_file'  => array( 'type' => 'string' ),
                    'version_from' => array( 'type' => 'string' ),
                    'version_to'   => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_update' ),
            'permission_callback' => function () {
                return current_user_can( 'update_plugins' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'           => 'Update Plugin',
                    'readOnlyHint'    => false,
                    'destructiveHint' => false,
                    'idempotentHint'  => true,
                    'openWorldHint'   => true,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/plugin-update', $ability_args );
    }

    public function execute_list(): array {
        $installed_plugins = get_plugins();
        $plugins           = array();

        foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
            $plugins[] = array(
                'slug'        => strpos( $plugin_file, '/' ) !== false ? dirname( $plugin_file ) : basename( $plugin_file, '.php' ),
                'plugin_file' => $plugin_file,
                'name'        => $plugin_data['Name'],
                'version'     => $plugin_data['Version'],
                'active'      => is_plugin_active( $plugin_file ),
            );
        }

        return array(
            'plugins' => $plugins,
        );
    }

    public function execute_install( array $input ): WP_Error|array {
        $slug              = sanitize_key( $input['slug'] );
        $installed_plugins = get_plugins();

        foreach ( array_keys( $installed_plugins ) as $plugin_file ) {
            if ( str_starts_with( $plugin_file, $slug . '/' ) || $plugin_file === $slug . '.php' ) {
                return new WP_Error(
                    'plugin_already_installed',
                    // translators: %s: plugin slug.
                    sprintf( __( 'Plugin "%s" is already installed.', 'hostinger-ai-assistant' ), $slug ),
                    array( 'status' => 409 )
                );
            }
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );
        $result   = $upgrader->install( 'https://downloads.wordpress.org/plugin/' . $slug . '.zip' );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! $result ) {
            return new WP_Error(
                'plugin_install_failed',
                // translators: %s: plugin slug.
                sprintf( __( 'Failed to install plugin "%s".', 'hostinger-ai-assistant' ), $slug ),
                array( 'status' => 500 )
            );
        }

        wp_cache_delete( 'plugins', 'plugins' );

        $all_plugins = get_plugins();
        $plugin_file = null;

        foreach ( array_keys( $all_plugins ) as $file ) {
            if ( str_starts_with( $file, $slug . '/' ) || $file === $slug . '.php' ) {
                $plugin_file = $file;
                break;
            }
        }

        if ( ! $plugin_file ) {
            return new WP_Error(
                'plugin_install_failed',
                // translators: %s: plugin slug.
                sprintf( __( 'Failed to locate installed plugin "%s".', 'hostinger-ai-assistant' ), $slug ),
                array( 'status' => 500 )
            );
        }

        $plugin_data = $all_plugins[ $plugin_file ];

        return array(
            'success'     => true,
            'plugin_file' => $plugin_file,
            'name'        => $plugin_data['Name'],
            'version'     => $plugin_data['Version'],
        );
    }

    public function execute_delete( array $input ): WP_Error|array {
        $plugin_file = sanitize_text_field( $input['plugin_file'] );

        if ( is_plugin_active( $plugin_file ) ) {
            return new WP_Error(
                'cannot_delete_active_plugin',
                __( 'Cannot delete an active plugin.', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        $installed_plugins = get_plugins();

        if ( ! array_key_exists( $plugin_file, $installed_plugins ) ) {
            return new WP_Error(
                'plugin_not_found',
                // translators: %s: plugin file path.
                sprintf( __( 'Plugin "%s" not found.', 'hostinger-ai-assistant' ), $plugin_file ),
                array( 'status' => 404 )
            );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $result = delete_plugins( array( $plugin_file ) );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! $result ) {
            return new WP_Error(
                'plugin_delete_failed',
                // translators: %s: plugin file path.
                sprintf( __( 'Failed to delete plugin "%s".', 'hostinger-ai-assistant' ), $plugin_file ),
                array( 'status' => 500 )
            );
        }

        return array(
            'success'     => true,
            'plugin_file' => $plugin_file,
        );
    }

    public function execute_activate( array $input ): WP_Error|array {
        $plugin_file       = sanitize_text_field( $input['plugin_file'] );
        $installed_plugins = get_plugins();

        if ( ! array_key_exists( $plugin_file, $installed_plugins ) ) {
            return new WP_Error(
                'plugin_not_found',
                // translators: %s: plugin file path.
                sprintf( __( 'Plugin "%s" not found.', 'hostinger-ai-assistant' ), $plugin_file ),
                array( 'status' => 404 )
            );
        }

        if ( is_plugin_active( $plugin_file ) ) {
            return array(
                'success'     => true,
                'plugin_file' => $plugin_file,
                'name'        => $installed_plugins[ $plugin_file ]['Name'],
            );
        }

        $result = activate_plugin( $plugin_file );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array(
            'success'     => true,
            'plugin_file' => $plugin_file,
            'name'        => $installed_plugins[ $plugin_file ]['Name'],
        );
    }

    public function execute_deactivate( array $input ): WP_Error|array {
        $plugin_file       = sanitize_text_field( $input['plugin_file'] );
        $installed_plugins = get_plugins();

        if ( ! array_key_exists( $plugin_file, $installed_plugins ) ) {
            return new WP_Error(
                'plugin_not_found',
                // translators: %s: plugin file path.
                sprintf( __( 'Plugin "%s" not found.', 'hostinger-ai-assistant' ), $plugin_file ),
                array( 'status' => 404 )
            );
        }

        if ( ! is_plugin_active( $plugin_file ) ) {
            return array(
                'success'     => true,
                'plugin_file' => $plugin_file,
                'name'        => $installed_plugins[ $plugin_file ]['Name'],
            );
        }

        deactivate_plugins( $plugin_file );

        return array(
            'success'     => true,
            'plugin_file' => $plugin_file,
            'name'        => $installed_plugins[ $plugin_file ]['Name'],
        );
    }

    public function execute_update( array $input ): WP_Error|array {
        $plugin_file       = sanitize_text_field( $input['plugin_file'] );
        $installed_plugins = get_plugins();

        if ( ! array_key_exists( $plugin_file, $installed_plugins ) ) {
            return new WP_Error(
                'plugin_not_found',
                // translators: %s: plugin file path.
                sprintf( __( 'Plugin "%s" not found.', 'hostinger-ai-assistant' ), $plugin_file ),
                array( 'status' => 404 )
            );
        }

        $version_from = $installed_plugins[ $plugin_file ]['Version'];
        $was_active   = is_plugin_active( $plugin_file );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        wp_update_plugins();

        $update_transient = get_site_transient( 'update_plugins' );

        if ( ! isset( $update_transient->response[ $plugin_file ] ) ) {
            return array(
                'success'      => true,
                'plugin_file'  => $plugin_file,
                'version_from' => $version_from,
                'version_to'   => $version_from,
            );
        }

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );
        $result   = $upgrader->upgrade( $plugin_file );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! $result ) {
            return new WP_Error(
                'plugin_update_failed',
                // translators: %s: plugin file path.
                sprintf( __( 'Failed to update plugin "%s".', 'hostinger-ai-assistant' ), $plugin_file ),
                array( 'status' => 500 )
            );
        }

        wp_cache_delete( 'plugins', 'plugins' );

        $updated_plugins = get_plugins();
        $version_to      = $updated_plugins[ $plugin_file ]['Version'] ?? $version_from;

        if ( $was_active ) {
            activate_plugin( $plugin_file );
        }

        return array(
            'success'      => true,
            'plugin_file'  => $plugin_file,
            'version_from' => $version_from,
            'version_to'   => $version_to,
        );
    }
}
