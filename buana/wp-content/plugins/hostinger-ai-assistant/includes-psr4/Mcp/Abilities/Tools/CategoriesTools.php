<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_REST_Terms_Controller;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class CategoriesTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/categories-list',
                    'label'       => __( 'List Categories', 'hostinger-ai-assistant' ),
                    'description' => __( 'List all WordPress post categories with pagination and filtering.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'List Categories',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai/categories-get',
                    'label'       => __( 'Get Category', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single WordPress category by ID. Returns the full category object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get Category',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/categories-create',
                    'label'       => __( 'Create Category', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WordPress post category. Requires a name.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Category',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/categories-update',
                    'label'       => __( 'Update Category', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WordPress category by ID. Only provided fields will be updated.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Category',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'                  => 'hostinger-ai/categories-delete',
                    'label'                      => __( 'Delete Category', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Delete a WordPress category by ID. This action cannot be undone.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'force' => array(
                                'type'        => 'boolean',
                                'description' => __( 'Force category deletion', 'hostinger-ai-assistant' ),
                            ),
                        ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Delete Category',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            WP_REST_Terms_Controller::class,
            '/wp/v2/categories',
            'category'
        );
    }
}
