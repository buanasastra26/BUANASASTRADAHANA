<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

trait HostingerPluginUpdateUri {
    private static string $default_plugin_update_uri = 'https://wp-update.hostinger.io/';
    private static string $canary_plugin_update_uri  = 'https://wp-update-canary.hostinger.io/';
    private static string $staging_plugin_update_uri = 'https://wp-update-stage.hostinger.io/';

    protected function get_plugin_update_uri(): string {
        if ( isset( $_SERVER['H_STAGING'] ) && filter_var( $_SERVER['H_STAGING'], FILTER_VALIDATE_BOOLEAN ) === true ) {
            return self::$staging_plugin_update_uri;
        }

        if ( isset( $_SERVER['H_CANARY'] ) && filter_var( $_SERVER['H_CANARY'], FILTER_VALIDATE_BOOLEAN ) === true ) {
            return self::$canary_plugin_update_uri;
        }

        return self::$default_plugin_update_uri;
    }

    protected function build_hostinger_download_url( string $slug ): string {
        return $this->get_plugin_update_uri() . '?action=download&slug=' . $slug;
    }
}

