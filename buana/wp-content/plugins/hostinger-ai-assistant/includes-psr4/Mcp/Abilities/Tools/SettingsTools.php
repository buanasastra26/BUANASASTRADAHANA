<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_REST_Settings_Controller;
use WP_REST_Response;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class SettingsTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'custom' => array(
                    'tool_name'   => 'hostinger-ai/wp-settings-get',
                    'label'       => __( 'Get General Settings', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get WordPress general site settings including title, description, timezone, and other configuration options.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get General Settings',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'                  => 'hostinger-ai/wp-settings-update',
                    'label'                      => __( 'Update General Settings', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Update WordPress general site settings. Only provided fields will be updated.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'properties' => array(
                            'title'                  => array(
                                'type'        => 'string',
                                'description' => __( 'Site title', 'hostinger-ai-assistant' ),
                            ),
                            'description'            => array(
                                'type'        => 'string',
                                'description' => __( 'Site tagline/description', 'hostinger-ai-assistant' ),
                            ),
                            'timezone_string'        => array(
                                'type'        => 'string',
                                'description' => __( 'Site timezone', 'hostinger-ai-assistant' ),
                            ),
                            'date_format'            => array(
                                'type'        => 'string',
                                'description' => __( 'Date format', 'hostinger-ai-assistant' ),
                            ),
                            'time_format'            => array(
                                'type'        => 'string',
                                'description' => __( 'Time format', 'hostinger-ai-assistant' ),
                            ),
                            'start_of_week'          => array(
                                'type'        => 'integer',
                                'description' => __( 'Start of week (0 = Sunday, 1 = Monday, etc.)', 'hostinger-ai-assistant' ),
                            ),
                            'language'               => array(
                                'type'        => 'string',
                                'description' => __( 'Site language', 'hostinger-ai-assistant' ),
                            ),
                            'use_smilies'            => array(
                                'type'        => 'boolean',
                                'description' => __( 'Convert emoticons to graphics', 'hostinger-ai-assistant' ),
                            ),
                            'default_category'       => array(
                                'type'        => 'integer',
                                'description' => __( 'Default post category', 'hostinger-ai-assistant' ),
                            ),
                            'default_post_format'    => array(
                                'type'        => 'string',
                                'description' => __( 'Default post format', 'hostinger-ai-assistant' ),
                            ),
                            'posts_per_page'         => array(
                                'type'        => 'integer',
                                'description' => __( 'Number of posts to show per page', 'hostinger-ai-assistant' ),
                            ),
                            'default_comment_status' => array(
                                'type'        => 'string',
                                'description' => __( 'Default comment status (open/closed)', 'hostinger-ai-assistant' ),
                            ),
                            'default_ping_status'    => array(
                                'type'        => 'string',
                                'description' => __( 'Default ping status (open/closed)', 'hostinger-ai-assistant' ),
                            ),
                        ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Update General Settings',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                    'skip_ids'                   => true,
                ),
            ),
            WP_REST_Settings_Controller::class,
            '/wp/v2/settings',
            'settings'
        );
    }

    protected function execute_operation( $config, array $input ): WP_Error|array {
        $operation = $config->get_operation();
        $route     = $config->get_route();
        $method    = $config->get_http_method_for_operation();

        $request = new WP_REST_Request( $method, $route );

        foreach ( $input as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = $response instanceof WP_REST_Response ? $response->get_data() : $response;

        return $data;
    }
}
