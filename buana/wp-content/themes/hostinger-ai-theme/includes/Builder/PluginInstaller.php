<?php

namespace Hostinger\AiTheme\Builder;

use Plugin_Upgrader;
use WP_Ajax_Upgrader_Skin;
use WP_Error;

defined( 'ABSPATH' ) || exit;

trait PluginInstaller {
    private static int $max_retry_attempts = 3;

    protected function install_plugin_with_retry( string $download_url, string $plugin_file, string $plugin_name ): bool|WP_Error {
        $this->load_plugin_dependencies();

        $download_result = $this->download_plugin_with_retry( $download_url, $plugin_name );

        if ( is_wp_error( $download_result ) ) {
            return $download_result;
        }

        $activate_result = activate_plugin( $plugin_file );

        if ( is_wp_error( $activate_result ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf(
                    'Hostinger AI Theme: Failed to activate %s after installation. Error: %s',
                    $plugin_name,
                    $activate_result->get_error_message()
                ) );
            }

            return new WP_Error(
                'plugin_activation_failed',
                sprintf(
                    /* translators: %s: plugin name */
                    __( 'Failed to activate %s. Please try activating it manually.', 'hostinger-ai-theme' ),
                    $plugin_name
                ),
                array(
                    'plugin_name'    => $plugin_name,
                    'plugin_file'    => $plugin_file,
                    'original_error' => $activate_result->get_error_message(),
                )
            );
        }

        return true;
    }

    private function download_plugin_with_retry( string $download_url, string $plugin_name ): bool|WP_Error {
        $last_error = null;

        for ( $attempt = 1; $attempt <= self::$max_retry_attempts; $attempt++ ) {
            $result = $this->attempt_plugin_download( $download_url, $plugin_name, $attempt );

            if ( $result === true ) {
                $this->log_download_event( 'success', $plugin_name, $attempt );
                return true;
            }

            $last_error = $result;

            if ( $attempt < self::$max_retry_attempts ) {
                $this->log_download_event( 'retry', $plugin_name, $attempt, $last_error );
            }
        }

        $this->log_download_event( 'failure', $plugin_name, self::$max_retry_attempts, $last_error );

        return new WP_Error(
            'plugin_download_failed',
            sprintf(
                /* translators: %s: plugin name */
                __( 'Failed to download %s after multiple attempts. Please try again later or install the plugin manually.', 'hostinger-ai-theme' ),
                $plugin_name
            ),
            array(
                'plugin_name'  => $plugin_name,
                'download_url' => $download_url,
                'attempts'     => self::$max_retry_attempts,
                'last_error'   => $last_error instanceof WP_Error ? $last_error->get_error_message() : 'Unknown error',
            )
        );
    }

    private function attempt_plugin_download( string $download_url, string $plugin_name, int $attempt ): bool|WP_Error {
        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );

        $install_result = $upgrader->install( $download_url );

        if ( is_wp_error( $install_result ) ) {
            return $this->create_detailed_error( 'download_failed', $plugin_name, $install_result, $attempt );
        }

        if ( $install_result === false ) {
            $skin_errors = $skin->get_errors();
            if ( is_wp_error( $skin_errors ) && $skin_errors->has_errors() ) {
                return $this->create_detailed_error( 'installation_failed', $plugin_name, $skin_errors, $attempt );
            }

            return new WP_Error(
                'installation_failed',
                sprintf( 'Plugin %s installation returned false', $plugin_name ),
                array( 'attempt' => $attempt )
            );
        }

        return true;
    }

    private function load_plugin_dependencies(): void {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    private function create_detailed_error( string $code, string $plugin_name, WP_Error $original_error, int $attempt ): WP_Error {
        return new WP_Error(
            $code,
            sprintf( '%s: %s', $plugin_name, $original_error->get_error_message() ),
            array(
                'plugin_name'    => $plugin_name,
                'attempt'        => $attempt,
                'original_code'  => $original_error->get_error_code(),
                'original_data'  => $original_error->get_error_data(),
            )
        );
    }

    private function log_download_event( string $type, string $plugin_name, int $attempt, ?WP_Error $error = null ): void {
        switch ( $type ) {
            case 'retry':
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( sprintf(
                        'Hostinger AI Theme: %s download attempt %d/%d failed. Error: %s. Retrying immediately.',
                        $plugin_name,
                        $attempt,
                        self::$max_retry_attempts,
                        $error ? $error->get_error_message() : 'Unknown error'
                    ) );
                }
                break;

            case 'success':
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $attempt > 1 ) {
                    error_log( sprintf(
                        'Hostinger AI Theme: %s downloaded successfully on attempt %d.',
                        $plugin_name,
                        $attempt
                    ) );
                }
                break;

            case 'failure':
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( sprintf(
                        'Hostinger AI Theme: CRITICAL - %s download failed after %d attempts. Final error: %s',
                        $plugin_name,
                        self::$max_retry_attempts,
                        $error ? $error->get_error_message() : 'Unknown error'
                    ) );
                }
                break;
        }
    }

    protected function is_plugin_installed( string $plugin_file ): bool {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $installed_plugins = get_plugins();

        return array_key_exists( $plugin_file, $installed_plugins );
    }

    protected function ensure_plugin_active( string $plugin_file, string $plugin_name ): bool|WP_Error {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( is_plugin_active( $plugin_file ) ) {
            return true;
        }

        $activate_result = activate_plugin( $plugin_file );

        if ( is_wp_error( $activate_result ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf(
                    'Hostinger AI Theme: Failed to activate %s. Error: %s',
                    $plugin_name,
                    $activate_result->get_error_message()
                ) );
            }

            return new WP_Error(
                'plugin_activation_failed',
                sprintf(
                    /* translators: %s: plugin name */
                    __( 'Failed to activate %s. Please try activating it manually.', 'hostinger-ai-theme' ),
                    $plugin_name
                ),
                array(
                    'plugin_name' => $plugin_name,
                    'plugin_file' => $plugin_file,
                )
            );
        }

        return true;
    }
}

