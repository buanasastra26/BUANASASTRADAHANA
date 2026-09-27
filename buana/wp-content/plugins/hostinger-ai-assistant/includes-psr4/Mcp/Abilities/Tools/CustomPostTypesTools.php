<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use Alley\WP\Block_Converter\Block_Converter;
use Exception;
use Hostinger\AiAssistant\Mcp\ContentUtils;
use WP_Query;
use WP_Error;
use stdClass;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class CustomPostTypesTools {
    public function register(): void {
        $this->register_list_post_types_tool();
        $this->register_search_tool();
        $this->register_get_tool();
        $this->register_create_tool();
        $this->register_update_tool();
        $this->register_delete_tool();
    }

    private function register_list_post_types_tool(): void {
        $post_types      = get_post_types( array( 'public' => true ), 'objects' );
        $post_type_names = array();

        foreach ( $post_types as $post_type ) {
            $post_type_names[] = strtolower( $post_type->labels->name );
        }

        $post_types_list = implode( ', ', $post_type_names );

        wp_register_ability(
            'hostinger-ai/cpt-list-types',
            array(
                'label'               => __( 'List Post Types', 'hostinger-ai-assistant' ),
                'description'         => sprintf(
                    /* translators: %s: post type names */
                    __( 'List all available WordPress custom post types including %s', 'hostinger-ai-assistant' ),
                    $post_types_list
                ),
                'category'            => 'hostinger-ai-assistant',
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => new stdClass(),
                ),
                'execute_callback'    => array( $this, 'list_post_types' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'tool',
                    ),
                    'annotations'  => array(
                        'title'         => 'List Post Types',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    private function register_search_tool(): void {
        $post_types      = get_post_types( array( 'public' => true ), 'objects' );
        $post_type_names = array();

        foreach ( $post_types as $post_type ) {
            $post_type_names[] = strtolower( $post_type->labels->name );
        }

        $post_types_list = implode( ', ', $post_type_names );

        wp_register_ability(
            'hostinger-ai/cpt-search',
            array(
                'label'               => __( 'Search Custom Post Types Posts', 'hostinger-ai-assistant' ),
                'description'         => sprintf(
                    /* translators: %s: post type names */
                    __( 'Search and filter WordPress custom post types posts including %s with pagination', 'hostinger-ai-assistant' ),
                    $post_types_list
                ),
                'category'            => 'hostinger-ai-assistant',
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_type' => array(
                            'type'        => 'string',
                            'description' => __( 'The custom post type to search', 'hostinger-ai-assistant' ),
                        ),
                        'search'    => array(
                            'type'        => 'string',
                            'description' => __( 'Search term to look for in post titles and content', 'hostinger-ai-assistant' ),
                        ),
                        'author'    => array(
                            'type'        => 'integer',
                            'description' => __( 'Filter by author ID', 'hostinger-ai-assistant' ),
                        ),
                        'status'    => array(
                            'type'        => 'string',
                            'description' => __( 'Filter by post status (publish, draft, pending, etc.)', 'hostinger-ai-assistant' ),
                        ),
                        'page'      => array(
                            'type'        => 'integer',
                            'description' => __( 'Page number for pagination (starts from 1)', 'hostinger-ai-assistant' ),
                            'default'     => 1,
                        ),
                        'per_page'  => array(
                            'type'        => 'integer',
                            'description' => __( 'Number of posts per page', 'hostinger-ai-assistant' ),
                            'default'     => 10,
                        ),
                    ),
                    'required'   => array( 'post_type' ),
                ),
                'execute_callback'    => array( $this, 'search_custom_post_types' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'tool',
                    ),
                    'annotations'  => array(
                        'title'         => 'Search Custom Post Types Posts',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    private function register_get_tool(): void {
        wp_register_ability(
            'hostinger-ai/cpt-get',
            array(
                'label'               => __( 'Get Custom Post Type Post', 'hostinger-ai-assistant' ),
                'description'         => __( 'Get a WordPress custom post type post by ID', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_type' => array(
                            'type'        => 'string',
                            'description' => __( 'The custom post type to get', 'hostinger-ai-assistant' ),
                        ),
                        'id'        => array(
                            'type'        => 'integer',
                            'description' => __( 'The ID of the post to get', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'post_type', 'id' ),
                ),
                'execute_callback'    => array( $this, 'get_custom_post_type' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'tool',
                    ),
                    'annotations'  => array(
                        'title'         => 'Get Custom Post Type Post',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    private function register_create_tool(): void {
        wp_register_ability(
            'hostinger-ai/cpt-create',
            array(
                'label'               => __( 'Create Custom Post Type Post', 'hostinger-ai-assistant' ),
                'description'         => __( 'Add a new WordPress custom post type post', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_type' => array(
                            'type'        => 'string',
                            'description' => __( 'The custom post type to create', 'hostinger-ai-assistant' ),
                        ),
                        'title'     => array(
                            'type'        => 'string',
                            'description' => __( 'The title of the post', 'hostinger-ai-assistant' ),
                        ),
                        'content'   => array(
                            'type'        => 'string',
                            'description' => __( 'The content of the post', 'hostinger-ai-assistant' ),
                        ),
                        'excerpt'   => array(
                            'type'        => 'string',
                            'description' => __( 'The excerpt of the post', 'hostinger-ai-assistant' ),
                        ),
                        'status'    => array(
                            'type'        => 'string',
                            'description' => __( 'The status of the post (publish, draft, pending, etc.)', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'post_type', 'title', 'content' ),
                ),
                'execute_callback'    => array( $this, 'add_custom_post_type' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'tool',
                    ),
                    'annotations'  => array(
                        'title'           => 'Add Custom Post Type Post',
                        'readOnlyHint'    => false,
                        'destructiveHint' => false,
                        'idempotentHint'  => false,
                        'openWorldHint'   => false,
                    ),
                ),
            )
        );
    }

    private function register_update_tool(): void {
        wp_register_ability(
            'hostinger-ai/cpt-update',
            array(
                'label'               => __( 'Update Custom Post Type Post', 'hostinger-ai-assistant' ),
                'description'         => __( 'Update a WordPress custom post type post by ID', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_type' => array(
                            'type'        => 'string',
                            'description' => __( 'The custom post type to update', 'hostinger-ai-assistant' ),
                        ),
                        'id'        => array(
                            'type'        => 'integer',
                            'description' => __( 'The ID of the post to update', 'hostinger-ai-assistant' ),
                        ),
                        'title'     => array(
                            'type'        => 'string',
                            'description' => __( 'The title of the post', 'hostinger-ai-assistant' ),
                        ),
                        'content'   => array(
                            'type'        => 'string',
                            'description' => __( 'The content of the post', 'hostinger-ai-assistant' ),
                        ),
                        'excerpt'   => array(
                            'type'        => 'string',
                            'description' => __( 'The excerpt of the post', 'hostinger-ai-assistant' ),
                        ),
                        'status'    => array(
                            'type'        => 'string',
                            'description' => __( 'The status of the post (publish, draft, pending, etc.)', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'post_type', 'id' ),
                ),
                'execute_callback'    => array( $this, 'update_custom_post_type' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'tool',
                    ),
                    'annotations'  => array(
                        'title'           => 'Update Custom Post Type Post',
                        'readOnlyHint'    => false,
                        'destructiveHint' => false,
                        'idempotentHint'  => true,
                        'openWorldHint'   => false,
                    ),
                ),
            )
        );
    }

    private function register_delete_tool(): void {
        wp_register_ability(
            'hostinger-ai/cpt-delete',
            array(
                'label'               => __( 'Delete Custom Post Type Post', 'hostinger-ai-assistant' ),
                'description'         => __( 'Delete a WordPress custom post type post by ID. This action cannot be undone.', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_type' => array(
                            'type'        => 'string',
                            'description' => __( 'The custom post type to delete', 'hostinger-ai-assistant' ),
                        ),
                        'id'        => array(
                            'type'        => 'integer',
                            'description' => __( 'The ID of the post to delete', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'post_type', 'id' ),
                ),
                'execute_callback'    => array( $this, 'delete_custom_post_type' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'tool',
                    ),
                    'annotations'  => array(
                        'title'           => 'Delete Custom Post Type Post',
                        'readOnlyHint'    => false,
                        'destructiveHint' => true,
                        'idempotentHint'  => true,
                        'openWorldHint'   => false,
                    ),
                ),
            )
        );
    }

    public function list_post_types( array $input ): array {
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        $results    = array();

        foreach ( $post_types as $post_type ) {
            $results[] = array(
                'slug'        => $post_type->name,
                'name'        => $post_type->labels->name,
                'description' => $post_type->description,
                'rest_base'   => $post_type->rest_base,
            );
        }

        return array( 'data' => $results );
    }

    public function search_custom_post_types( array $input ): WP_Error|array {
        $post_type = sanitize_text_field( $input['post_type'] );

        if ( ! post_type_exists( $post_type ) ) {
            return new WP_Error(
                'invalid_post_type',
                __( 'Invalid post type', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        $page     = isset( $input['page'] ) ? max( 1, intval( $input['page'] ) ) : 1;
        $per_page = isset( $input['per_page'] ) ? max( 1, intval( $input['per_page'] ) ) : 10;

        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
        );

        if ( ! empty( $input['search'] ) ) {
            $args['s'] = sanitize_text_field( $input['search'] );
        }

        if ( ! empty( $input['author'] ) ) {
            $args['author'] = intval( $input['author'] );
        }

        if ( ! empty( $input['status'] ) ) {
            $args['post_status'] = sanitize_text_field( $input['status'] );
        }

        $query = new WP_Query( $args );

        return array(
            'data'     => $query->posts,
            'total'    => $query->found_posts,
            'pages'    => $query->max_num_pages,
            'page'     => $page,
            'per_page' => $per_page,
        );
    }

    public function get_custom_post_type( array $input ): WP_Error|array {
        $post_type = sanitize_text_field( $input['post_type'] );
        $post_id   = intval( $input['id'] );

        if ( ! post_type_exists( $post_type ) ) {
            return new WP_Error(
                'invalid_post_type',
                __( 'Invalid post type', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        $post = get_post( $post_id );

        if ( ! $post || $post->post_type !== $post_type ) {
            return new WP_Error(
                'post_not_found',
                __( 'Post not found', 'hostinger-ai-assistant' ),
                array( 'status' => 404 )
            );
        }

        return (array) $post;
    }

    public function add_custom_post_type( array $input ): WP_Error|array {
        $post_type = sanitize_text_field( $input['post_type'] );

        if ( ! post_type_exists( $post_type ) ) {
            return new WP_Error(
                'invalid_post_type',
                __( 'Invalid post type', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        $post_content = wp_kses_post( $input['content'] );
        $post_content = $this->maybe_convert_to_blocks( $post_content, $post_type );

        $post_data = array(
            'post_type'    => $post_type,
            'post_title'   => sanitize_text_field( $input['title'] ),
            'post_content' => $post_content,
            'post_status'  => 'draft',
        );

        if ( ! empty( $input['excerpt'] ) ) {
            $post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
        }

        if ( ! empty( $input['status'] ) ) {
            $post_data['post_status'] = sanitize_text_field( $input['status'] );
        }

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        return (array) get_post( $post_id );
    }

    public function update_custom_post_type( array $input ): WP_Error|array {
        $post_type = sanitize_text_field( $input['post_type'] );
        $post_id   = intval( $input['id'] );

        if ( ! post_type_exists( $post_type ) ) {
            return new WP_Error(
                'invalid_post_type',
                __( 'Invalid post type', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        $post = get_post( $post_id );

        if ( ! $post || $post->post_type !== $post_type ) {
            return new WP_Error(
                'post_not_found',
                __( 'Post not found', 'hostinger-ai-assistant' ),
                array( 'status' => 404 )
            );
        }

        $post_data = array(
            'ID' => $post_id,
        );

        if ( ! empty( $input['title'] ) ) {
            $post_data['post_title'] = sanitize_text_field( $input['title'] );
        }

        if ( ! empty( $input['content'] ) ) {
            $post_data['post_content'] = wp_kses_post( $input['content'] );
        }

        if ( ! empty( $input['excerpt'] ) ) {
            $post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
        }

        if ( ! empty( $input['status'] ) ) {
            $post_data['post_status'] = sanitize_text_field( $input['status'] );
        }

        $updated_id = wp_update_post( $post_data );

        if ( is_wp_error( $updated_id ) ) {
            return $updated_id;
        }

        return (array) get_post( $updated_id );
    }

    public function delete_custom_post_type( array $input ): WP_Error|array {
        $post_type = sanitize_text_field( $input['post_type'] );
        $post_id   = intval( $input['id'] );

        if ( ! post_type_exists( $post_type ) ) {
            return new WP_Error(
                'invalid_post_type',
                __( 'Invalid post type', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        $post = get_post( $post_id );

        if ( ! $post || $post->post_type !== $post_type ) {
            return new WP_Error(
                'post_not_found',
                __( 'Post not found', 'hostinger-ai-assistant' ),
                array( 'status' => 404 )
            );
        }

        $result = wp_delete_post( $post_id, true );

        if ( ! $result ) {
            return new WP_Error(
                'delete_failed',
                __( 'Failed to delete post', 'hostinger-ai-assistant' ),
                array( 'status' => 500 )
            );
        }

        return array(
            'deleted'  => true,
            'previous' => $post,
        );
    }

    private function maybe_convert_to_blocks( string $content, string $post_type ): string {
        if ( empty( $content ) ) {
            return $content;
        }

        if ( ! use_block_editor_for_post_type( $post_type ) ) {
            return $content;
        }

        if ( ContentUtils::is_block_content( $content ) ) {
            return $content;
        }

        try {
            $converter = new Block_Converter( $content );

            return $converter->convert();
        } catch ( Exception $e ) {
            error_log( 'MCP Block conversion error: ' . $e->getMessage() );

            return $content;
        }
    }
}
