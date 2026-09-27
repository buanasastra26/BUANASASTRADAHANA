<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_REST_Posts_Controller;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class PostsTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/posts-search',
                    'label'       => __( 'Search Posts', 'hostinger-ai-assistant' ),
                    'description' => __( 'Search and filter WordPress posts with pagination. Returns a list of posts matching the search criteria.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Search Posts',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai/posts-get',
                    'label'       => __( 'Get Post', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single WordPress post by ID. Returns the full post object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get Post',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/posts-create',
                    'label'       => __( 'Create Post', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WordPress post. Requires title and content.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Post',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/posts-update',
                    'label'       => __( 'Update Post', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WordPress post by ID. Only provided fields will be updated.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Post',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai/posts-delete',
                    'label'       => __( 'Delete Post', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a WordPress post by ID. This action cannot be undone.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Post',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            WP_REST_Posts_Controller::class,
            '/wp/v2/posts',
            'post'
        );
    }
}
