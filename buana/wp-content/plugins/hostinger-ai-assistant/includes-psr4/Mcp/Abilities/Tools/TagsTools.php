<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_REST_Terms_Controller;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class TagsTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/tags-list',
                    'label'       => __( 'List Tags', 'hostinger-ai-assistant' ),
                    'description' => __( 'List all WordPress post tags with pagination and filtering.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'List Tags',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai/tags-get',
                    'label'       => __( 'Get Tag', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single WordPress tag by ID. Returns the full tag object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get Tag',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/tags-create',
                    'label'       => __( 'Create Tag', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WordPress post tag. Requires a name.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Tag',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/tags-update',
                    'label'       => __( 'Update Tag', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WordPress tag by ID. Only provided fields will be updated.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Tag',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'                  => 'hostinger-ai/tags-delete',
                    'label'                      => __( 'Delete Tag', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Delete a WordPress tag by ID. This action cannot be undone.', 'hostinger-ai-assistant' ),
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
                            'title'           => 'Delete Tag',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            WP_REST_Terms_Controller::class,
            '/wp/v2/tags',
            'post_tag'
        );
    }
}
