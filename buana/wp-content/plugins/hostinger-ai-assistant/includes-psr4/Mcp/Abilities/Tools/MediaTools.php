<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_REST_Attachments_Controller;
use WP_Error;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class MediaTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/media-list',
                    'label'       => __( 'List Media', 'hostinger-ai-assistant' ),
                    'description' => __( 'List WordPress media items with pagination and filtering.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'List Media',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai/media-get',
                    'label'       => __( 'Get Media', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a WordPress media item details by ID. Returns the full media object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get Media',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/media-update',
                    'label'       => __( 'Update Media', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update a WordPress media item metadata. Only provided fields will be updated.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Media',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai/media-delete',
                    'label'       => __( 'Delete Media', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a WordPress media item permanently. This action cannot be undone.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Media',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            WP_REST_Attachments_Controller::class,
            '/wp/v2/media',
            'attachment'
        );

        $this->register_search_tool();
        $this->register_get_file_tool();
    }

    private function register_search_tool(): void {
        wp_register_ability(
            'hostinger-ai/media-search',
            array(
                'label'               => __( 'Search Media', 'hostinger-ai-assistant' ),
                'description'         => __( 'Search WordPress media items by title, caption, or description with filtering options.', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'search'     => array(
                            'type'        => 'string',
                            'description' => __( 'Search term to look for in media titles, captions, and descriptions', 'hostinger-ai-assistant' ),
                        ),
                        'media_type' => array(
                            'type'        => 'string',
                            'description' => __( 'Filter by media type (image, video, audio, application)', 'hostinger-ai-assistant' ),
                        ),
                        'mime_type'  => array(
                            'type'        => 'string',
                            'description' => __( 'Filter by MIME type (e.g., image/jpeg, video/mp4)', 'hostinger-ai-assistant' ),
                        ),
                        'author'     => array(
                            'type'        => 'integer',
                            'description' => __( 'Filter by author ID', 'hostinger-ai-assistant' ),
                        ),
                        'parent'     => array(
                            'type'        => 'integer',
                            'description' => __( 'Filter by parent post ID', 'hostinger-ai-assistant' ),
                        ),
                        'page'       => array(
                            'type'        => 'integer',
                            'description' => __( 'Page number for pagination', 'hostinger-ai-assistant' ),
                            'default'     => 1,
                        ),
                        'per_page'   => array(
                            'type'        => 'integer',
                            'description' => __( 'Number of items per page', 'hostinger-ai-assistant' ),
                            'default'     => 10,
                        ),
                    ),
                ),
                'execute_callback'    => array( $this, 'search_media' ),
                'permission_callback' => function () {
                    return current_user_can( 'upload_files' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'tool',
                    ),
                    'annotations'  => array(
                        'title'         => 'Search Media',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    private function register_get_file_tool(): void {
        wp_register_ability(
            'hostinger-ai/media-get-file',
            array(
                'label'               => __( 'Get Media File', 'hostinger-ai-assistant' ),
                'description'         => __( 'Get the actual file content (blob) of a WordPress media item.', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'id'   => array(
                            'type'        => 'integer',
                            'description' => __( 'The ID of the media item', 'hostinger-ai-assistant' ),
                        ),
                        'size' => array(
                            'type'        => 'string',
                            'description' => __( 'Optional. The size of the image to retrieve (thumbnail, medium, large, full). Defaults to full/original size.', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'id' ),
                ),
                'execute_callback'    => array( $this, 'get_media_file' ),
                'permission_callback' => function () {
                    return current_user_can( 'upload_files' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'tool',
                    ),
                    'annotations'  => array(
                        'title'         => 'Get Media File',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    public function search_media( array $input ): WP_Error|array {
        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => isset( $input['per_page'] ) ? intval( $input['per_page'] ) : 10,
            'paged'          => isset( $input['page'] ) ? intval( $input['page'] ) : 1,
        );

        if ( ! empty( $input['search'] ) ) {
            $args['s'] = sanitize_text_field( $input['search'] );
        }

        if ( ! empty( $input['media_type'] ) ) {
            $args['post_mime_type'] = sanitize_text_field( $input['media_type'] );
        }

        if ( ! empty( $input['mime_type'] ) ) {
            $args['post_mime_type'] = sanitize_text_field( $input['mime_type'] );
        }

        if ( ! empty( $input['author'] ) ) {
            $args['author'] = intval( $input['author'] );
        }

        if ( ! empty( $input['parent'] ) ) {
            $args['post_parent'] = intval( $input['parent'] );
        }

        $query = new WP_Query( $args );

        return array(
            'data'     => $query->posts,
            'total'    => $query->found_posts,
            'pages'    => $query->max_num_pages,
            'page'     => $args['paged'],
            'per_page' => $args['posts_per_page'],
        );
    }

    public function get_media_file( array $input ): WP_Error|array {
        $id   = intval( $input['id'] );
        $size = isset( $input['size'] ) ? sanitize_text_field( $input['size'] ) : 'full';

        if ( ! $id ) {
            return new WP_Error( 'invalid_media_id', __( 'Invalid media ID', 'hostinger-ai-assistant' ), array( 'status' => 400 ) );
        }

        $file_path = get_attached_file( $id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'File not found', 'hostinger-ai-assistant' ), array( 'status' => 404 ) );
        }

        if ( $size !== 'full' && $size !== 'original' ) {
            $meta = wp_get_attachment_metadata( $id );
            if ( isset( $meta['sizes'][ $size ]['file'] ) ) {
                $base_dir  = pathinfo( $file_path, PATHINFO_DIRNAME );
                $file_path = $base_dir . '/' . $meta['sizes'][ $size ]['file'];
            }
        }

        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'size_not_found', __( 'Requested size not found', 'hostinger-ai-assistant' ), array( 'status' => 404 ) );
        }

        $mime_type = get_post_mime_type( $id );
        $file_data = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        return array(
            'results'  => $file_data,
            'type'     => 'image',
            'mimeType' => $mime_type,
        );
    }
}
