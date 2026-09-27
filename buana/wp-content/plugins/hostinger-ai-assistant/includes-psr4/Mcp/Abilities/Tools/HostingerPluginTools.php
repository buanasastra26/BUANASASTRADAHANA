<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use Hostinger\Admin\PluginSettings;
use stdClass;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class HostingerPluginTools {
    protected string $type = 'tool';

    public function register(): void {
        if ( ! $this->is_hostinger_plugin_active() ) {
            return;
        }

        $this->register_get_operation();
        $this->register_update_operation();
    }

    private function register_get_operation(): void {
        $ability_args = array(
            'label'               => __( 'Get Hostinger Plugin Settings', 'hostinger-ai-assistant' ),
            'description'         => __( 'Get current Hostinger plugin settings including maintenance mode, XML-RPC, HTTPS, and other security options.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => new stdClass(),
            ),
            'output_schema'       => $this->output_schema(),
            'execute_callback'    => array( $this, 'execute_get_operation' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'         => 'Get Hostinger Plugin Settings',
                    'readOnlyHint'  => true,
                    'openWorldHint' => false,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/hostinger-plugin-settings-get', $ability_args );
    }

    private function register_update_operation(): void {
        $ability_args = array(
            'label'               => __( 'Update Hostinger Plugin Settings', 'hostinger-ai-assistant' ),
            'description'         => __( 'Update Hostinger plugin settings. Only provided fields will be updated.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'maintenance_mode'                => array(
                        'type'        => 'boolean',
                        'description' => __( 'Enable or disable maintenance mode', 'hostinger-ai-assistant' ),
                    ),
                    'bypass_code'                     => array(
                        'type'        => 'string',
                        'description' => __( 'Bypass code for maintenance mode', 'hostinger-ai-assistant' ),
                    ),
                    'disable_xml_rpc'                 => array(
                        'type'        => 'boolean',
                        'description' => __( 'Enable or disable XML-RPC', 'hostinger-ai-assistant' ),
                    ),
                    'force_https'                     => array(
                        'type'        => 'boolean',
                        'description' => __( 'Force HTTPS redirects', 'hostinger-ai-assistant' ),
                    ),
                    'force_www'                       => array(
                        'type'        => 'boolean',
                        'description' => __( 'Force WWW in URLs', 'hostinger-ai-assistant' ),
                    ),
                    'disable_authentication_password' => array(
                        'type'        => 'boolean',
                        'description' => __( 'Disable authentication password', 'hostinger-ai-assistant' ),
                    ),
                    'enable_llms_txt'                 => array(
                        'type'        => 'boolean',
                        'description' => __( 'Enable llms.txt generation', 'hostinger-ai-assistant' ),
                    ),
                    'optin_mcp'                       => array(
                        'type'        => 'boolean',
                        'description' => __( 'Opt-in to MCP', 'hostinger-ai-assistant' ),
                    ),
                ),
            ),
            'output_schema'       => $this->output_schema(),
            'execute_callback'    => array( $this, 'execute_update_operation' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'           => 'Update Hostinger Plugin Settings',
                    'readOnlyHint'    => false,
                    'destructiveHint' => false,
                    'idempotentHint'  => true,
                    'openWorldHint'   => false,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/hostinger-plugin-settings-update', $ability_args );
    }

    public function execute_get_operation(): WP_Error|array {
        if ( ! defined( 'HOSTINGER_PLUGIN_SETTINGS_OPTION' ) ) {
            return new WP_Error(
                'hostinger_plugin_not_configured',
                __( 'Hostinger plugin settings option not defined', 'hostinger-ai-assistant' ),
                array( 'status' => 500 )
            );
        }

        $plugin_settings = new PluginSettings();
        $settings        = $plugin_settings->get_plugin_settings();

        return $settings->to_array();
    }

    public function execute_update_operation( array $input ): WP_Error|array {
        if ( ! defined( 'HOSTINGER_PLUGIN_SETTINGS_OPTION' ) ) {
            return new WP_Error(
                'hostinger_plugin_not_configured',
                __( 'Hostinger plugin settings option not defined', 'hostinger-ai-assistant' ),
                array( 'status' => 500 )
            );
        }

        $plugin_settings = new PluginSettings();
        $settings        = $plugin_settings->get_plugin_settings();

        if ( isset( $input['maintenance_mode'] ) ) {
            $settings->set_maintenance_mode( (bool) $input['maintenance_mode'] );
        }
        if ( isset( $input['bypass_code'] ) ) {
            $settings->set_bypass_code( (string) $input['bypass_code'] );
        }
        if ( isset( $input['disable_xml_rpc'] ) ) {
            $settings->set_disable_xml_rpc( (bool) $input['disable_xml_rpc'] );
        }
        if ( isset( $input['force_https'] ) ) {
            $settings->set_force_https( (bool) $input['force_https'] );
        }
        if ( isset( $input['force_www'] ) ) {
            $settings->set_force_www( (bool) $input['force_www'] );
        }
        if ( isset( $input['disable_authentication_password'] ) ) {
            $settings->set_disable_authentication_password( (bool) $input['disable_authentication_password'] );
        }
        if ( isset( $input['enable_llms_txt'] ) ) {
            $settings->set_enable_llms_txt( (bool) $input['enable_llms_txt'] );
        }
        if ( isset( $input['optin_mcp'] ) ) {
            $settings->set_optin_mcp( (bool) $input['optin_mcp'] );
        }

        $updated_settings = $plugin_settings->save_plugin_settings( $settings );

        if ( isset( $input['maintenance_mode'] ) ) {
            do_action( 'litespeed_purge_all' );
        }

        return $updated_settings->to_array();
    }

    public function output_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'maintenance_mode'                => array(
                    'type'        => 'boolean',
                    'description' => __( 'Maintenance mode status', 'hostinger-ai-assistant' ),
                ),
                'bypass_code'                     => array(
                    'type'        => 'string',
                    'description' => __( 'Bypass code for maintenance mode. Needed only when maintenance mode is enabled.', 'hostinger-ai-assistant' ),
                ),
                'disable_xml_rpc'                 => array(
                    'type'        => 'boolean',
                    'description' => __( 'Disable XML-RPC status', 'hostinger-ai-assistant' ),
                ),
                'force_https'                     => array(
                    'type'        => 'boolean',
                    'description' => __( 'Force HTTPS status', 'hostinger-ai-assistant' ),
                ),
                'force_www'                       => array(
                    'type'        => 'boolean',
                    'description' => __( 'Force WWW status', 'hostinger-ai-assistant' ),
                ),
                'disable_authentication_password' => array(
                    'type'        => 'boolean',
                    'description' => __( 'Disable authentication password status', 'hostinger-ai-assistant' ),
                ),
                'enable_llms_txt'                 => array(
                    'type'        => 'boolean',
                    'description' => __( 'Enable llms.txt status', 'hostinger-ai-assistant' ),
                ),
                'optin_mcp'                       => array(
                    'type'        => 'boolean',
                    'description' => __( 'Opt-in Web2Agent MCP status', 'hostinger-ai-assistant' ),
                ),
            ),
        );
    }

    private function is_hostinger_plugin_active(): bool {
        return defined( 'HOSTINGER_PLUGIN_SETTINGS_OPTION' )
            && class_exists( 'Hostinger\Admin\Options\PluginOptions' )
            && class_exists( 'Hostinger\Admin\PluginSettings' );
    }
}
