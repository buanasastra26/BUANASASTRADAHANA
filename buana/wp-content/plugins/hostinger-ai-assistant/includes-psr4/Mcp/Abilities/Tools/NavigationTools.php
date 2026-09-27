<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class NavigationTools extends RestEndpointTool {
    public function register(): void {

        if ( ! class_exists( 'WP_REST_Navigation_Controller' ) ) {
            return;
        }

        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/navigation-search',
                    'label'       => __( 'List Navigations', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a list of Site Editor navigations.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'List Navigations',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai/navigation-get',
                    'label'       => __( 'Get Navigation', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single navigation by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get Navigation',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/navigation-create',
                    'label'       => __( 'Create Navigation', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new Site Editor navigation.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Navigation',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/navigation-update',
                    'label'       => __( 'Update Navigation', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing Site Editor navigation.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Navigation',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai/navigation-delete',
                    'label'       => __( 'Delete Navigation', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a Site Editor navigation by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Navigation',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WP_REST_Navigation_Controller',
            '/wp/v2/navigation',
            'wp_navigation'
        );
    }
}
