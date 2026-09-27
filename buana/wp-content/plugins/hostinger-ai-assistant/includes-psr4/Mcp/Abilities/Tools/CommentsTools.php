<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_Comment;
use WP_REST_Comments_Controller;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class CommentsTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'                  => 'hostinger-ai/comments-search',
                    'label'                      => __( 'Search Comments', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Search and filter WordPress comments with pagination. Use status to filter by moderation state (approve, hold, spam, trash, all) and type to filter by comment type (comment, review for WooCommerce product reviews).', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'properties' => array(
                            'status' => array(
                                'description' => __( 'Moderation status to filter by: approve, hold (pending), spam, trash or all.', 'hostinger-ai-assistant' ),
                            ),
                            'type'   => array(
                                'description' => __( 'Comment type to filter by, for example comment or review. Use an empty value to return all types.', 'hostinger-ai-assistant' ),
                            ),
                        ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'         => 'Search Comments',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                    'permission_callback'        => function () {
                        return current_user_can( 'moderate_comments' );
                    },
                ),
                'get'    => array(
                    'tool_name'           => 'hostinger-ai/comments-get',
                    'label'               => __( 'Get Comment', 'hostinger-ai-assistant' ),
                    'description'         => __( 'Get a single WordPress comment by ID. Returns the full comment object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'                => array(
                        'annotations' => array(
                            'title'         => 'Get Comment',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                    'permission_callback' => function () {
                        return current_user_can( 'moderate_comments' );
                    },
                ),
                'create' => array(
                    'tool_name'                  => 'hostinger-ai/comments-create',
                    'label'                      => __( 'Create Comment', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Create a new WordPress comment on a post. Requires the post ID and the comment content.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'properties' => array(
                            'post'    => array(
                                'description' => __( 'ID of the post the comment belongs to.', 'hostinger-ai-assistant' ),
                            ),
                            'content' => array(
                                'type'        => 'string',
                                'description' => __( 'Content of the comment.', 'hostinger-ai-assistant' ),
                                'properties'  => null,
                            ),
                        ),
                        'required'   => array( 'post', 'content' ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Add Comment',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                    'permission_callback'        => function () {
                        return current_user_can( 'moderate_comments' );
                    },
                ),
                'update' => array(
                    'tool_name'                  => 'hostinger-ai/comments-update',
                    'label'                      => __( 'Update Comment', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Update an existing WordPress comment by ID. Only provided fields will be updated. Use status to change the moderation state (approved, hold, spam, trash).', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'properties' => array(
                            'content' => array(
                                'type'        => 'string',
                                'description' => __( 'Content of the comment.', 'hostinger-ai-assistant' ),
                                'properties'  => null,
                            ),
                        ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Update Comment',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                    'permission_callback'        => function () {
                        return current_user_can( 'moderate_comments' );
                    },
                ),
                'delete' => array(
                    'tool_name'                  => 'hostinger-ai/comments-delete',
                    'label'                      => __( 'Delete Comment', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Delete a WordPress comment by ID. The comment is moved to trash unless force is true, which deletes it permanently.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'force' => array(
                                'type'        => 'boolean',
                                'description' => __( 'Skip the trash and delete the comment permanently.', 'hostinger-ai-assistant' ),
                            ),
                        ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Delete Comment',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                    'permission_callback'        => function () {
                        return current_user_can( 'moderate_comments' );
                    },
                ),
            ),
            WP_REST_Comments_Controller::class,
            '/wp/v2/comments',
            'comment'
        );

        $this->register_moderation_operations();
    }

    private function register_moderation_operations(): void {
        $operations = array(
            'hostinger-ai/comments-approve'   => array(
                'label'       => __( 'Approve Comment', 'hostinger-ai-assistant' ),
                'description' => __( 'Approve a WordPress comment by ID, making it publicly visible.', 'hostinger-ai-assistant' ),
                'title'       => 'Approve Comment',
                'action'      => 'approve',
            ),
            'hostinger-ai/comments-unapprove' => array(
                'label'       => __( 'Unapprove Comment', 'hostinger-ai-assistant' ),
                'description' => __( 'Unapprove a WordPress comment by ID, moving it back to pending moderation.', 'hostinger-ai-assistant' ),
                'title'       => 'Unapprove Comment',
                'action'      => 'unapprove',
            ),
            'hostinger-ai/comments-spam'      => array(
                'label'       => __( 'Mark Comment As Spam', 'hostinger-ai-assistant' ),
                'description' => __( 'Mark a WordPress comment as spam by ID.', 'hostinger-ai-assistant' ),
                'title'       => 'Mark Comment As Spam',
                'action'      => 'spam',
            ),
            'hostinger-ai/comments-unspam'    => array(
                'label'       => __( 'Unmark Comment As Spam', 'hostinger-ai-assistant' ),
                'description' => __( 'Restore a WordPress comment that was marked as spam by ID. The comment returns to its previous moderation status.', 'hostinger-ai-assistant' ),
                'title'       => 'Unmark Comment As Spam',
                'action'      => 'unspam',
            ),
        );

        foreach ( $operations as $tool_name => $operation ) {
            wp_register_ability(
                $tool_name,
                array(
                    'label'               => $operation['label'],
                    'description'         => $operation['description'],
                    'category'            => 'hostinger-ai-assistant',
                    'input_schema'        => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id' => array(
                                'type'        => 'integer',
                                'description' => __( 'Unique identifier for the comment', 'hostinger-ai-assistant' ),
                            ),
                        ),
                        'required'   => array( 'id' ),
                    ),
                    'execute_callback'    => function ( $input ) use ( $operation ) {
                        return $this->execute_moderation( $operation['action'], is_array( $input ) ? $input : array() );
                    },
                    'permission_callback' => function () {
                        return current_user_can( 'moderate_comments' );
                    },
                    'meta'                => array(
                        'show_in_rest' => true,
                        'mcp'          => array(
                            'public' => true,
                            'type'   => $this->type,
                        ),
                        'annotations'  => array(
                            'title'           => $operation['title'],
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                )
            );
        }
    }

    private function execute_moderation( string $action, array $input ): array {
        $comment_id = isset( $input['id'] ) ? intval( $input['id'] ) : 0;

        if ( $comment_id <= 0 ) {
            return array(
                'success'    => false,
                'error_code' => 'INVALID_PARAMS',
                'message'    => __( 'A valid comment ID is required.', 'hostinger-ai-assistant' ),
            );
        }

        $comment = get_comment( $comment_id );

        if ( ! $comment instanceof WP_Comment ) {
            return array(
                'success'    => false,
                'error_code' => 'COMMENT_NOT_FOUND',
                /* translators: %s: comment id */
                'message'    => sprintf( __( "Comment with ID '%s' not found", 'hostinger-ai-assistant' ), $comment_id ),
            );
        }

        if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
            return array(
                'success'    => false,
                'error_code' => 'FORBIDDEN',
                'message'    => __( 'You are not allowed to moderate this comment.', 'hostinger-ai-assistant' ),
            );
        }

        if ( $action === 'unspam' && wp_get_comment_status( $comment_id ) !== 'spam' ) {
            return array(
                'success'    => false,
                'error_code' => 'NOT_SPAM',
                'message'    => __( 'The comment is not marked as spam, so its status was left unchanged.', 'hostinger-ai-assistant' ),
            );
        }

        $result = $this->apply_moderation_action( $action, $comment_id );

        if ( ! $result ) {
            return array(
                'success'    => false,
                'error_code' => 'NOT_UPDATED',
                'message'    => __( 'The comment moderation status was not changed.', 'hostinger-ai-assistant' ),
            );
        }

        $updated_comment = get_comment( $comment_id );

        return array(
            'success' => true,
            'action'  => $action,
            'comment' => array(
                'id'     => $comment_id,
                'status' => $updated_comment instanceof WP_Comment ? wp_get_comment_status( $comment_id ) : 'deleted',
                'post'   => $updated_comment instanceof WP_Comment ? intval( $updated_comment->comment_post_ID ) : 0,
            ),
        );
    }

    private function apply_moderation_action( string $action, int $comment_id ): bool {
        switch ( $action ) {
            case 'approve':
                return (bool) wp_set_comment_status( $comment_id, 'approve' );
            case 'unapprove':
                return (bool) wp_set_comment_status( $comment_id, 'hold' );
            case 'spam':
                return (bool) wp_spam_comment( $comment_id );
            case 'unspam':
                return (bool) wp_unspam_comment( $comment_id );
        }

        return false;
    }
}
