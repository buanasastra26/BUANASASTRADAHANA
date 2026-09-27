<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ListTemplates extends BaseElementorTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-list-templates',
            array(
                'label'               => __( 'List Elementor Templates', 'hostinger-ai-assistant' ),
                'description'         => __( 'Retrieves Elementor templates from the elementor_library post type. Results include the template type and support pagination.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_status' => array(
                            'type'        => 'string',
                            'description' => __( 'Filter by post status (publish, draft, any). Default: any', 'hostinger-ai-assistant' ),
                            'default'     => 'any',
                            'enum'        => array( 'publish', 'draft', 'pending', 'private', 'any' ),
                        ),
                        'limit'       => array(
                            'type'        => 'integer',
                            'description' => __( 'Number of templates to return. Default: 50', 'hostinger-ai-assistant' ),
                            'default'     => 50,
                            'minimum'     => 1,
                            'maximum'     => 100,
                        ),
                        'offset'      => array(
                            'type'        => 'integer',
                            'description' => __( 'Number of templates to skip for pagination. Default: 0', 'hostinger-ai-assistant' ),
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
                        'title'         => 'List Elementor Templates',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    public function execute( array $input ): array {
        $post_status = $input['post_status'] ?? 'any';
        $limit       = $input['limit'] ?? 50;
        $offset      = $input['offset'] ?? 0;

        $args = array(
            'post_type'      => 'elementor_library',
            'post_status'    => $post_status,
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'perm'           => 'readable',
        );

        $query = new \WP_Query( $args );

        $templates = array();
        foreach ( $query->posts as $post ) {
            $template_type     = get_post_meta( $post->ID, '_elementor_template_type', true );
            $elementor_version = get_post_meta( $post->ID, '_elementor_version', true );

            $templates[] = array(
                'id'                => $post->ID,
                'title'             => $post->post_title,
                'post_status'       => $post->post_status,
                'edit_url'          => get_edit_post_link( $post->ID ),
                'template_type'     => $template_type ? $template_type : null,
                'elementor_version' => $elementor_version ? $elementor_version : 'unknown',
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
            'templates' => $templates,
            'total'     => $count_query->found_posts,
        );
    }
}
