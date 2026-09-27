<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Revisions_Controller;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class RevisionsTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'                  => 'hostinger-ai/revisions-list',
                    'label'                      => __( 'List Revisions', 'hostinger-ai-assistant' ),
                    'description'                => __( 'List revisions for a specific item. Provide its REST API base (e.g., posts, pages, templates) and the parent ID.', 'hostinger-ai-assistant' ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'         => 'List Revisions',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                    'input_schema_modifications' => array(
                        'properties' => array(
                            'base_api' => array(
                                'type'        => 'string',
                                'description' => __( 'REST API base for the post type (e.g., posts, pages, templates, product, etc.).', 'hostinger-ai-assistant' ),
                            ),
                            'parent'   => array(
                                'type'        => 'integer',
                                'description' => __( 'The parent item ID to list revisions for.', 'hostinger-ai-assistant' ),
                            ),
                        ),
                        'required'   => array( 'base_api', 'parent' ),
                    ),
                    'skip_ids'                   => true,
                ),
                'get'    => array(
                    'tool_name'                  => 'hostinger-ai/revisions-get',
                    'label'                      => __( 'Get Revision', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Get a specific revision for an item. Provide REST API base and parent ID, plus the revision ID.', 'hostinger-ai-assistant' ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'         => 'Get Revision',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                    'input_schema_modifications' => array(
                        'properties' => array(
                            'base_api' => array(
                                'type'        => 'string',
                                'description' => __( 'REST API base for the post type (e.g., posts, pages, templates, product, etc.).', 'hostinger-ai-assistant' ),
                            ),
                            'parent'   => array(
                                'type'        => 'integer',
                                'description' => __( 'The parent item ID.', 'hostinger-ai-assistant' ),
                            ),
                        ),
                        'required'   => array( 'base_api', 'parent', 'id' ),
                    ),
                ),
                'delete' => array(
                    'tool_name'                  => 'hostinger-ai/revisions-delete',
                    'label'                      => __( 'Delete Revision', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Delete a specific revision for an item. Provide REST API base and parent ID, plus the revision ID.', 'hostinger-ai-assistant' ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Delete Revision',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                    'input_schema_modifications' => array(
                        'properties' => array(
                            'base_api' => array(
                                'type'        => 'string',
                                'description' => __( 'REST API base for the post type (e.g., posts, pages, templates, product, etc.).', 'hostinger-ai-assistant' ),
                            ),
                            'parent'   => array(
                                'type'        => 'integer',
                                'description' => __( 'The parent item ID.', 'hostinger-ai-assistant' ),
                            ),
                        ),
                        'required'   => array( 'base_api', 'parent', 'id' ),
                    ),
                ),
            ),
            WP_REST_Revisions_Controller::class,
            '/wp/v2/(?P<base_api>[a-zA-Z0-9_-]+)/(?P<parent>[\\d]+)/revisions',
            'post'
        );

        $this->register_restore_revision_ability();
    }

    private function register_restore_revision_ability(): void {
        $tool_name = 'hostinger-ai/revisions-restore';

        wp_register_ability(
            $tool_name,
            array(
                'label'               => __( 'Restore Revision', 'hostinger-ai-assistant' ),
                'description'         => __( 'Restore a specific revision for an item by copying its content back to the parent. Works for any post type using its REST base.', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'base_api'    => array(
                            'type'        => 'string',
                            'description' => __( 'REST API base for the post type (e.g., posts, pages, templates, product, etc.).', 'hostinger-ai-assistant' ),
                        ),
                        'parent'      => array(
                            'type'        => 'integer',
                            'description' => __( 'ID of the parent item to restore into.', 'hostinger-ai-assistant' ),
                        ),
                        'revision_id' => array(
                            'type'        => 'integer',
                            'description' => __( 'ID of the revision to restore from.', 'hostinger-ai-assistant' ),
                        ),
                        'fields'      => array(
                            'type'        => 'array',
                            'items'       => array(
                                'type' => 'string',
                                'enum' => array( 'title', 'content', 'excerpt' ),
                            ),
                            'description' => __( 'Optional list of fields to restore. Defaults to title, content, and excerpt.', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'base_api', 'parent', 'revision_id' ),
                ),
                'execute_callback'    => function ( $input ) {
                    return $this->execute( is_array( $input ) ? $input : array() );
                },
                'permission_callback' => function () {
                    return current_user_can( 'edit_posts' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'tool',
                    ),
                    'annotations'  => array(
                        'title'           => 'Restore Revision',
                        'readOnlyHint'    => false,
                        'destructiveHint' => false,
                        'idempotentHint'  => false,
                        'openWorldHint'   => false,
                    ),
                ),
            )
        );
    }

    private function execute( array $input ): array {
        $base_api    = isset( $input['base_api'] ) && is_string( $input['base_api'] ) ? trim( (string) $input['base_api'] ) : '';
        $parent_id   = isset( $input['parent'] ) ? intval( $input['parent'] ) : 0;
        $revision_id = isset( $input['revision_id'] ) ? intval( $input['revision_id'] ) : 0;
        $fields      = isset( $input['fields'] ) && is_array( $input['fields'] ) && ! empty( $input['fields'] ) ? $input['fields'] : array( 'title', 'content', 'excerpt' );

        if ( $base_api === '' || $parent_id <= 0 || $revision_id <= 0 ) {
            return array(
                'success'    => false,
                'error_code' => 'INVALID_PARAMS',
                'message'    => __( 'Invalid base API, parent, or revision ID.', 'hostinger-ai-assistant' ),
            );
        }

        $base_route = '/wp/v2/' . $base_api;

        $get_revision = new WP_REST_Request( 'GET', $base_route . '/' . $parent_id . '/revisions/' . $revision_id );
        $rev_response = rest_do_request( $get_revision );
        if ( is_wp_error( $rev_response ) ) {
            return $rev_response;
        }
        $rev_data = $rev_response instanceof WP_REST_Response ? $rev_response->get_data() : $rev_response;

        if ( empty( $rev_data ) || ! is_array( $rev_data ) ) {
            return array(
                'success'    => false,
                'error_code' => 'REVISION_NOT_FOUND',
                /* translators: %s: revision id  */
                'message'    => sprintf( __( "Revision with ID '%s' not found", 'hostinger-ai-assistant' ), $revision_id ),
            );
        }

        $update_payload = array();
        if ( in_array( 'title', $fields, true ) && isset( $rev_data['title']['rendered'] ) ) {
            $update_payload['title'] = $rev_data['title']['rendered'];
        }
        if ( in_array( 'content', $fields, true ) && isset( $rev_data['content']['rendered'] ) ) {
            $update_payload['content'] = $rev_data['content']['rendered'];
        }
        if ( in_array( 'excerpt', $fields, true ) && isset( $rev_data['excerpt']['rendered'] ) ) {
            $update_payload['excerpt'] = $rev_data['excerpt']['rendered'];
        }

        if ( empty( $update_payload ) ) {
            return array(
                'success'    => false,
                'error_code' => 'NOT_UPDATED',
                'message'    => __( 'Nothing was updated', 'hostinger-ai-assistant' ),
            );
        }

        $update_request = new WP_REST_Request( 'PUT', $base_route . '/' . $parent_id );
        foreach ( $update_payload as $k => $v ) {
            $update_request->set_param( $k, $v );
        }

        $update_response = rest_do_request( $update_request );
        if ( is_wp_error( $update_response ) ) {
            return array(
                'success'    => false,
                'error_code' => 'WP_ERROR',
                'message'    => __( 'It was an error processing the request.', 'hostinger-ai-assistant' ),
            );
        }

        $updated = $update_response instanceof WP_REST_Response ? $update_response->get_data() : $update_response;

        return array(
            'success'       => true,
            'restored_from' => array(
                'id'       => $revision_id,
                'base_api' => $base_api,
                'parent'   => $parent_id,
                'fields'   => $fields,
            ),
            'updated'       => $updated,
        );
    }
}
