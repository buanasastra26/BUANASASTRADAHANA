<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class GetPageStructure extends BaseElementorTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-get-page-structure',
            array(
                'label'               => __( 'Get Elementor Page Structure', 'hostinger-ai-assistant' ),
                'description'         => __( 'Returns a readable, hierarchical summary of Elementor page structure without raw JSON. Shows containers and widgets in a tree view. Each container reports its existing background (video/image/gradient/color) in a "background" field — always check this before suggesting or adding a background, so you do not overwrite an existing video/image background.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_id'          => array(
                            'type'        => 'integer',
                            'description' => __( 'The ID of the post/page to get structure for', 'hostinger-ai-assistant' ),
                        ),
                        'max_depth'        => array(
                            'type'        => 'integer',
                            'description' => __( 'Maximum recursion depth to display. Default: 10', 'hostinger-ai-assistant' ),
                            'default'     => 10,
                            'minimum'     => 1,
                            'maximum'     => 200,
                        ),
                        'include_settings' => array(
                            'type'        => 'boolean',
                            'description' => __( 'Whether to include settings. Default: false', 'hostinger-ai-assistant' ),
                            'default'     => false,
                        ),
                    ),
                    'required'   => array( 'post_id' ),
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
                        'title'         => 'Get Page Structure',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    public function execute( array $input ): array {
        $post_id          = $input['post_id'];
        $max_depth        = $input['max_depth'] ?? 10;
        $include_settings = $input['include_settings'] ?? false;

        $post = get_post( $post_id );
        $data = get_post_meta( $post_id, '_elementor_data', true );

        if ( empty( $data ) ) {
            return array(
                'post_id'    => $post_id,
                'post_title' => $post->post_title,
                'structure'  => array(),
            );
        }

        $elementor_data = json_decode( $data, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return array(
                'error_code' => 'INVALID_JSON',
                'message'    => 'Could not parse Elementor data',
            );
        }

        $structure = $this->build_structure_tree( $elementor_data, 0, $max_depth, $include_settings );

        return array(
            'post_id'    => $post_id,
            'post_title' => $post->post_title,
            'structure'  => $structure,
        );
    }
}
