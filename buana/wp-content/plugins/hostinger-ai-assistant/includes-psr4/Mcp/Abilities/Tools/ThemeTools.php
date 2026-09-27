<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_Error;
use WP_Ajax_Upgrader_Skin;
use Theme_Upgrader;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ThemeTools {
    protected string $type = 'tool';

    public function register(): void {
        $this->register_install_operation();
        $this->register_delete_operation();
        $this->register_activate_operation();
        $this->register_deactivate_operation();
        $this->register_update_operation();
    }

    private function register_install_operation(): void {
        $ability_args = array(
            'label'               => __( 'Install Theme', 'hostinger-ai-assistant' ),
            'description'         => __( 'Install a theme from WordPress.org by slug.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'required'   => array( 'slug' ),
                'properties' => array(
                    'slug' => array(
                        'type'        => 'string',
                        'description' => __( "WordPress.org theme slug (e.g. 'twentytwentyfour')", 'hostinger-ai-assistant' ),
                    ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success'    => array( 'type' => 'boolean' ),
                    'stylesheet' => array( 'type' => 'string' ),
                    'name'       => array( 'type' => 'string' ),
                    'version'    => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_install' ),
            'permission_callback' => function () {
                return current_user_can( 'install_themes' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'           => 'Install Theme',
                    'readOnlyHint'    => false,
                    'destructiveHint' => false,
                    'idempotentHint'  => false,
                    'openWorldHint'   => true,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/theme-install', $ability_args );
    }

    private function register_delete_operation(): void {
        $ability_args = array(
            'label'               => __( 'Delete Theme', 'hostinger-ai-assistant' ),
            'description'         => __( 'Delete an installed theme by stylesheet directory name. Cannot delete the active or parent theme.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'required'   => array( 'stylesheet' ),
                'properties' => array(
                    'stylesheet' => array(
                        'type'        => 'string',
                        'description' => __( "Theme stylesheet directory name (e.g. 'twentytwentyfour')", 'hostinger-ai-assistant' ),
                    ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success'    => array( 'type' => 'boolean' ),
                    'stylesheet' => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_delete' ),
            'permission_callback' => function () {
                return current_user_can( 'delete_themes' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'           => 'Delete Theme',
                    'readOnlyHint'    => false,
                    'destructiveHint' => true,
                    'idempotentHint'  => false,
                    'openWorldHint'   => true,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/theme-delete', $ability_args );
    }

    private function register_activate_operation(): void {
        $ability_args = array(
            'label'               => __( 'Activate Theme', 'hostinger-ai-assistant' ),
            'description'         => __( 'Activate an installed theme by stylesheet directory name.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'required'   => array( 'stylesheet' ),
                'properties' => array(
                    'stylesheet' => array(
                        'type'        => 'string',
                        'description' => __( 'Theme stylesheet directory name to activate', 'hostinger-ai-assistant' ),
                    ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success'    => array( 'type' => 'boolean' ),
                    'stylesheet' => array( 'type' => 'string' ),
                    'name'       => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_activate' ),
            'permission_callback' => function () {
                return current_user_can( 'switch_themes' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'           => 'Activate Theme',
                    'readOnlyHint'    => false,
                    'destructiveHint' => false,
                    'idempotentHint'  => true,
                    'openWorldHint'   => true,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/theme-activate', $ability_args );
    }

    private function register_deactivate_operation(): void {
        $ability_args = array(
            'label'               => __( 'Deactivate Theme', 'hostinger-ai-assistant' ),
            'description'         => __( 'Deactivate the currently active theme by switching to a fallback theme.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'required'   => array( 'stylesheet' ),
                'properties' => array(
                    'stylesheet'          => array(
                        'type'        => 'string',
                        'description' => __( 'Theme stylesheet directory name to deactivate (will switch to the default theme)', 'hostinger-ai-assistant' ),
                    ),
                    'fallback_stylesheet' => array(
                        'type'        => 'string',
                        'description' => __( "Stylesheet of the theme to activate as a replacement. Defaults to 'twentytwentyfour'.", 'hostinger-ai-assistant' ),
                    ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success'              => array( 'type' => 'boolean' ),
                    'deactivated'          => array( 'type' => 'string' ),
                    'activated_stylesheet' => array( 'type' => 'string' ),
                    'activated_name'       => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_deactivate' ),
            'permission_callback' => function () {
                return current_user_can( 'switch_themes' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'           => 'Deactivate Theme',
                    'readOnlyHint'    => false,
                    'destructiveHint' => false,
                    'idempotentHint'  => false,
                    'openWorldHint'   => true,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/theme-deactivate', $ability_args );
    }

    private function register_update_operation(): void {
        $ability_args = array(
            'label'               => __( 'Update Theme', 'hostinger-ai-assistant' ),
            'description'         => __( 'Update an installed theme to the latest version from WordPress.org.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'required'   => array( 'stylesheet' ),
                'properties' => array(
                    'stylesheet' => array(
                        'type'        => 'string',
                        'description' => __( 'Theme stylesheet directory name to update', 'hostinger-ai-assistant' ),
                    ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success'      => array( 'type' => 'boolean' ),
                    'stylesheet'   => array( 'type' => 'string' ),
                    'version_from' => array( 'type' => 'string' ),
                    'version_to'   => array( 'type' => 'string' ),
                ),
            ),
            'execute_callback'    => array( $this, 'execute_update' ),
            'permission_callback' => function () {
                return current_user_can( 'update_themes' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'           => 'Update Theme',
                    'readOnlyHint'    => false,
                    'destructiveHint' => false,
                    'idempotentHint'  => true,
                    'openWorldHint'   => true,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/theme-update', $ability_args );
    }

    public function execute_install( array $input ): WP_Error|array {
        $slug = sanitize_key( $input['slug'] );

        if ( wp_get_theme( $slug )->exists() ) {
            return new WP_Error(
                'theme_already_installed',
                // translators: %s: theme slug.
                sprintf( __( 'Theme "%s" is already installed.', 'hostinger-ai-assistant' ), $slug ),
                array( 'status' => 409 )
            );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Theme_Upgrader( $skin );
        $result   = $upgrader->install( 'https://downloads.wordpress.org/theme/' . $slug . '.zip' );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! $result ) {
            return new WP_Error(
                'theme_install_failed',
                // translators: %s: theme slug.
                sprintf( __( 'Failed to install theme "%s".', 'hostinger-ai-assistant' ), $slug ),
                array( 'status' => 500 )
            );
        }

        $theme = wp_get_theme( $slug );

        return array(
            'success'    => true,
            'stylesheet' => $theme->get_stylesheet(),
            'name'       => $theme->get( 'Name' ),
            'version'    => $theme->get( 'Version' ),
        );
    }

    public function execute_delete( array $input ): WP_Error|array {
        $stylesheet = sanitize_key( $input['stylesheet'] );

        if ( get_stylesheet() === $stylesheet ) {
            return new WP_Error(
                'cannot_delete_active_theme',
                __( 'Cannot delete the active theme.', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        if ( get_template() === $stylesheet ) {
            return new WP_Error(
                'cannot_delete_parent_theme',
                __( 'Cannot delete the active parent theme.', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        if ( ! wp_get_theme( $stylesheet )->exists() ) {
            return new WP_Error(
                'theme_not_found',
                // translators: %s: theme stylesheet directory name.
                sprintf( __( 'Theme "%s" not found.', 'hostinger-ai-assistant' ), $stylesheet ),
                array( 'status' => 404 )
            );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';

        $result = delete_theme( $stylesheet );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! $result ) {
            return new WP_Error(
                'theme_delete_failed',
                // translators: %s: theme stylesheet directory name.
                sprintf( __( 'Failed to delete theme "%s".', 'hostinger-ai-assistant' ), $stylesheet ),
                array( 'status' => 500 )
            );
        }

        return array(
            'success'    => true,
            'stylesheet' => $stylesheet,
        );
    }

    public function execute_activate( array $input ): WP_Error|array {
        $stylesheet = sanitize_key( $input['stylesheet'] );

        if ( ! wp_get_theme( $stylesheet )->exists() ) {
            return new WP_Error(
                'theme_not_found',
                // translators: %s: theme stylesheet directory name.
                sprintf( __( 'Theme "%s" not found.', 'hostinger-ai-assistant' ), $stylesheet ),
                array( 'status' => 404 )
            );
        }

        if ( get_stylesheet() === $stylesheet ) {
            $theme = wp_get_theme( $stylesheet );

            return array(
                'success'    => true,
                'stylesheet' => $stylesheet,
                'name'       => $theme->get( 'Name' ),
            );
        }

        switch_theme( $stylesheet );

        $theme = wp_get_theme( $stylesheet );

        return array(
            'success'    => true,
            'stylesheet' => $stylesheet,
            'name'       => $theme->get( 'Name' ),
        );
    }

    public function execute_deactivate( array $input ): WP_Error|array {
        $stylesheet = sanitize_key( $input['stylesheet'] );

        if ( get_stylesheet() !== $stylesheet ) {
            return new WP_Error(
                'theme_not_active',
                // translators: %s: theme stylesheet directory name.
                sprintf( __( 'Theme "%s" is not the active theme.', 'hostinger-ai-assistant' ), $stylesheet ),
                array( 'status' => 400 )
            );
        }

        $fallback = $this->resolve_fallback_theme( $stylesheet, $input['fallback_stylesheet'] ?? '' );

        if ( is_wp_error( $fallback ) ) {
            return $fallback;
        }

        switch_theme( $fallback );

        $activated_theme = wp_get_theme( $fallback );

        return array(
            'success'              => true,
            'deactivated'          => $stylesheet,
            'activated_stylesheet' => $fallback,
            'activated_name'       => $activated_theme->get( 'Name' ),
        );
    }

    public function execute_update( array $input ): WP_Error|array {
        $stylesheet = sanitize_key( $input['stylesheet'] );

        if ( ! wp_get_theme( $stylesheet )->exists() ) {
            return new WP_Error(
                'theme_not_found',
                // translators: %s: theme stylesheet directory name.
                sprintf( __( 'Theme "%s" not found.', 'hostinger-ai-assistant' ), $stylesheet ),
                array( 'status' => 404 )
            );
        }

        $theme        = wp_get_theme( $stylesheet );
        $version_from = $theme->get( 'Version' );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';

        wp_update_themes();

        $update_transient = get_site_transient( 'update_themes' );

        if ( ! isset( $update_transient->response[ $stylesheet ] ) ) {
            return array(
                'success'      => true,
                'stylesheet'   => $stylesheet,
                'version_from' => $version_from,
                'version_to'   => $version_from,
            );
        }

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Theme_Upgrader( $skin );
        $result   = $upgrader->upgrade( $stylesheet );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! $result ) {
            return new WP_Error(
                'theme_update_failed',
                // translators: %s: theme stylesheet directory name.
                sprintf( __( 'Failed to update theme "%s".', 'hostinger-ai-assistant' ), $stylesheet ),
                array( 'status' => 500 )
            );
        }

        $version_to = wp_get_theme( $stylesheet )->get( 'Version' );

        return array(
            'success'      => true,
            'stylesheet'   => $stylesheet,
            'version_from' => $version_from,
            'version_to'   => $version_to,
        );
    }

    private function resolve_fallback_theme( string $active_stylesheet, string $requested_fallback ): WP_Error|string {
        if ( $requested_fallback !== '' ) {
            $fallback = sanitize_key( $requested_fallback );

            if ( ! wp_get_theme( $fallback )->exists() ) {
                return new WP_Error(
                    'fallback_theme_not_found',
                    // translators: %s: theme slug name.
                    sprintf( __( 'Fallback theme "%s" not found.', 'hostinger-ai-assistant' ), $fallback ),
                    array( 'status' => 404 )
                );
            }

            return $fallback;
        }

        if ( wp_get_theme( 'twentytwentyfour' )->exists() ) {
            return 'twentytwentyfour';
        }

        $all_themes = wp_get_themes();

        foreach ( $all_themes as $theme_stylesheet => $theme ) {
            if ( $theme_stylesheet !== $active_stylesheet ) {
                return $theme_stylesheet;
            }
        }

        return new WP_Error(
            'no_fallback_theme_available',
            __( 'No fallback theme available to switch to.', 'hostinger-ai-assistant' ),
            array( 'status' => 400 )
        );
    }
}
