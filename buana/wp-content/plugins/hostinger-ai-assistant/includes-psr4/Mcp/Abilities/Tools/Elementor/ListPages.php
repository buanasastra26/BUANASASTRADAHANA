<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ListPages extends BaseElementorTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-list-pages',
            array(
                'label'               => __( 'List Elementor Pages', 'hostinger-ai-assistant' ),
                'description'         => __( 'Retrieves all posts/pages/custom post types that use Elementor builder. Supports filtering by post type and status with pagination.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_type'   => array(
                            'type'        => 'string',
                            'description' => __( 'Filter by post type (page, post, any). Default: any', 'hostinger-ai-assistant' ),
                            'default'     => 'any',
                            'enum'        => array( 'page', 'post', 'any' ),
                        ),
                        'post_status' => array(
                            'type'        => 'string',
                            'description' => __( 'Filter by post status (publish, draft, any). Default: any', 'hostinger-ai-assistant' ),
                            'default'     => 'any',
                            'enum'        => array( 'publish', 'draft', 'pending', 'private', 'any' ),
                        ),
                        'limit'       => array(
                            'type'        => 'integer',
                            'description' => __( 'Number of pages to return. Default: 50', 'hostinger-ai-assistant' ),
                            'default'     => 50,
                            'minimum'     => 1,
                            'maximum'     => 100,
                        ),
                        'offset'      => array(
                            'type'        => 'integer',
                            'description' => __( 'Number of pages to skip for pagination. Default: 0', 'hostinger-ai-assistant' ),
                            'default'     => 0,
                            'minimum'     => 0,
                        ),
                    ),
                ),
                'execute_callback'    => array( $this, 'execute' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => $this->type,
                    ),
                    'annotations'  => array(
                        'title'         => 'List Elementor Pages',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    public function execute( array $input ): array {
        $post_type   = $input['post_type'] ?? 'any';
        $post_status = $input['post_status'] ?? 'any';
        $limit       = $input['limit'] ?? 50;
        $offset      = $input['offset'] ?? 0;

        $args = array(
            'post_type'      => $post_type,
            'post_status'    => $post_status,
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'meta_query'     => array(
                array(
                    'key'   => '_elementor_edit_mode',
                    'value' => 'builder',
                ),
            ),
            'orderby'        => 'modified',
            'order'          => 'DESC',
        );

        $query = new \WP_Query( $args );

        $pages = array();
        foreach ( $query->posts as $post ) {
            $elementor_version = get_post_meta( $post->ID, '_elementor_version', true );
            $template_type     = get_post_meta( $post->ID, '_elementor_template_type', true );

            $pages[] = array(
                'id'                => $post->ID,
                'title'             => $post->post_title,
                'post_type'         => $post->post_type,
                'post_status'       => $post->post_status,
                'edit_url'          => get_edit_post_link( $post->ID ),
                'elementor_version' => $elementor_version ?? 'unknown',
                'has_template_type' => $template_type ? 'yes' : 'no',
                'template_type'     => $template_type ?? null,
                'modified'          => $post->post_modified,
            );
        }

        $count_args           = $args;
        $count_args['limit']  = -1;
        $count_args['fields'] = 'ids';
        unset( $count_args['posts_per_page'] );
        unset( $count_args['offset'] );

        $count_query = new \WP_Query( $count_args );

        return array(
            'pages' => $pages,
            'total' => $count_query->found_posts,
        );
    }
}
