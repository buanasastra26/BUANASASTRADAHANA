<?php

namespace Hostinger\AiAssistant\Hosting;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ReachRepository {
    private const REACH_API_KEY_OPTION         = 'hostinger_reach_api_key';
    private const REACH_CONNECTION_TIME_OPTION = 'hostinger_reach_api_connection_time';
    private const REACH_PLUGIN                 = 'hostinger-reach/hostinger-reach.php';

    public function is_reach_active(): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( self::REACH_PLUGIN );
    }

    public function is_setup_complete(): bool {
        if ( ! empty( get_option( self::REACH_API_KEY_OPTION, '' ) ) ) {
            return true;
        }

        return ! empty( get_option( self::REACH_CONNECTION_TIME_OPTION, '' ) );
    }
}
