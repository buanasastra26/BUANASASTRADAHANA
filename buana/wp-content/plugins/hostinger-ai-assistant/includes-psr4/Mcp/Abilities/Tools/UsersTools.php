<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_REST_Users_Controller;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class UsersTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'                  => 'hostinger-ai/users-search',
                    'label'                      => __( 'Search Users', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Search and filter WordPress users with pagination. Returns a list of users matching the search criteria.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'properties' => array(
                            'has_published_posts' => array(
                                'items' => array(
                                    'enum' => array(
                                        'post',
                                        'page',
                                    ),
                                ),
                            ),
                        ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'         => 'Search Users',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                    'permission_callback'        => function () {
                        return current_user_can( 'list_users' );
                    },
                ),
                'get'    => array(
                    'tool_name'           => 'hostinger-ai/users-get',
                    'label'               => __( 'Get User', 'hostinger-ai-assistant' ),
                    'description'         => __( 'Get a single WordPress user by ID. Returns the full user object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'                => array(
                        'annotations' => array(
                            'title'         => 'Get User',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                    'permission_callback' => function () {
                        return current_user_can( 'list_users' );
                    },
                ),
                'create' => array(
                    'tool_name'           => 'hostinger-ai/users-create',
                    'label'               => __( 'Create User', 'hostinger-ai-assistant' ),
                    'description'         => __( 'Create a new WordPress user. Requires username and email.', 'hostinger-ai-assistant' ),
                    'meta'                => array(
                        'annotations' => array(
                            'title'           => 'Add User',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                    'permission_callback' => function () {
                        return current_user_can( 'create_users' );
                    },
                ),
                'update' => array(
                    'tool_name'           => 'hostinger-ai/users-update',
                    'label'               => __( 'Update User', 'hostinger-ai-assistant' ),
                    'description'         => __( 'Update an existing WordPress user by ID. Only provided fields will be updated.', 'hostinger-ai-assistant' ),
                    'meta'                => array(
                        'annotations' => array(
                            'title'           => 'Update User',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                    'permission_callback' => function () {
                        return current_user_can( 'edit_users' );
                    },
                ),
                'delete' => array(
                    'tool_name'           => 'hostinger-ai/users-delete',
                    'label'               => __( 'Delete User', 'hostinger-ai-assistant' ),
                    'description'         => __( 'Delete a WordPress user by ID. This action cannot be undone.', 'hostinger-ai-assistant' ),
                    'meta'                => array(
                        'annotations' => array(
                            'title'           => 'Delete User',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                    'permission_callback' => function () {
                        return current_user_can( 'delete_users' );
                    },
                ),
            ),
            WP_REST_Users_Controller::class,
            '/wp/v2/users',
            'user'
        );

        $this->register_current_user_operations();
    }

    private function register_current_user_operations(): void {
        $this->register_operations(
            array(
                'get'    => array(
                    'tool_name'           => 'hostinger-ai/users-get-current',
                    'label'               => __( 'Get Current User', 'hostinger-ai-assistant' ),
                    'description'         => __( 'Get the current logged-in user information.', 'hostinger-ai-assistant' ),
                    'meta'                => array(
                        'annotations' => array(
                            'title'         => 'Get Current User',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                    'permission_callback' => function () {
                        return is_user_logged_in();
                    },
                ),
                'update' => array(
                    'tool_name'           => 'hostinger-ai/users-update-current',
                    'label'               => __( 'Update Current User', 'hostinger-ai-assistant' ),
                    'description'         => __( 'Update the current logged-in user information.', 'hostinger-ai-assistant' ),
                    'meta'                => array(
                        'annotations' => array(
                            'title'           => 'Update Current User',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                    'permission_callback' => function () {
                        return is_user_logged_in();
                    },
                ),
            ),
            WP_REST_Users_Controller::class,
            '/wp/v2/users/me',
            'user'
        );
    }
}
