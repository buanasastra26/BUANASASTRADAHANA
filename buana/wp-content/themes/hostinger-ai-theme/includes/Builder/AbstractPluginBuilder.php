<?php

namespace Hostinger\AiTheme\Builder;

use WP_Error;

defined( 'ABSPATH' ) || exit;

abstract class AbstractPluginBuilder {
    use PluginInstaller;

    abstract protected function get_plugin_file(): string;

    abstract protected function get_plugin_name(): string;

    abstract protected function get_download_url(): string;

    abstract protected function get_error_code(): string;

    protected function is_enabled(): bool {
        return true;
    }

    protected function after_installation(): void {
    }

    protected function after_activation(): void {
    }

    public function boot(): bool|WP_Error {
        if ( ! $this->is_enabled() ) {
            return true;
        }

        return $this->force_boot();
    }

    public function force_boot(): bool|WP_Error {
        $enable_result = $this->enable_plugin();
        if ( is_wp_error( $enable_result ) ) {
            return $enable_result;
        }

        if ( ! $this->is_plugin_active() ) {
            return new WP_Error(
                $this->get_error_code() . '_not_active',
                sprintf(
                    /* translators: %s: plugin name */
                    __( '%s is not active after installation.', 'hostinger-ai-theme' ),
                    $this->get_plugin_name()
                )
            );
        }

        $this->after_activation();

        return true;
    }

    public function enable_plugin(): bool|WP_Error {
        if ( $this->is_plugin_installed( $this->get_plugin_file() ) ) {
            $activate_result = $this->ensure_plugin_active( $this->get_plugin_file(), $this->get_plugin_name() );

            if ( is_wp_error( $activate_result ) ) {
                return $activate_result;
            }

            return true;
        }

        return $this->install_plugin();
    }

    public function install_plugin(): bool|WP_Error {
        $result = $this->install_plugin_with_retry(
            $this->get_download_url(),
            $this->get_plugin_file(),
            $this->get_plugin_name()
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $this->after_installation();

        return true;
    }

    public function is_plugin_active(): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return \is_plugin_active( $this->get_plugin_file() );
    }

    // Deactivates the plugin but leaves it installed, so its data survives a regeneration.
    public function disable_plugin(): void {
        if ( ! $this->is_plugin_active() ) {
            return;
        }

        deactivate_plugins( $this->get_plugin_file() );

        if ( $this->is_plugin_active() ) {
            Helper::log( 'Failed to deactivate plugin ' . $this->get_plugin_file() );
        }
    }
}

