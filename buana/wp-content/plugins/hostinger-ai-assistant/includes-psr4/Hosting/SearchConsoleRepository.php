<?php

namespace Hostinger\AiAssistant\Hosting;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class SearchConsoleRepository {
    public const SITE_KIT_SLUG             = 'google-site-kit';
    private const SITE_KIT_PLUGIN          = 'google-site-kit/google-site-kit.php';
    private const SITE_KIT_SETTINGS_OPTION = 'googlesitekit_search-console_settings';

    public function is_site_kit_active(): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( self::SITE_KIT_PLUGIN );
    }

    public function is_site_kit_installed(): bool {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return array_key_exists( self::SITE_KIT_PLUGIN, get_plugins() );
    }

    public function is_connected(): bool {
        if ( ! $this->is_site_kit_active() ) {
            return false;
        }

        $settings = get_option( self::SITE_KIT_SETTINGS_OPTION, array() );

        if ( ! is_array( $settings ) ) {
            return false;
        }

        return ! empty( $settings['propertyID'] );
    }
}
