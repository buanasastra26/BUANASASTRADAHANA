<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

use Hostinger\AiAssistant\Mcp\Dto\Elementor\WidgetSearchQuery;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class FindWidgets extends BaseElementorTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-find-widgets',
            array(
                'label'               => __( 'Find Elementor Widgets', 'hostinger-ai-assistant' ),
                'description'         => __( 'Searches for specific widget types in an Elementor page structure. Returns all widgets matching the criteria with their settings and position.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_id'          => array(
                            'type'        => 'integer',
                            'description' => __( 'The ID of the post/page to search in', 'hostinger-ai-assistant' ),
                        ),
                        'widget_types'     => array(
                            'type'        => 'array',
                            'description' => __( 'Array of widget types to search for (e.g., ["button", "heading", "image"])', 'hostinger-ai-assistant' ),
                            'items'       => array(
                                'type' => 'string',
                            ),
                        ),
                        'max_depth'        => array(
                            'type'        => 'integer',
                            'description' => __( 'Maximum search depth. Default: 10', 'hostinger-ai-assistant' ),
                            'default'     => 10,
                            'minimum'     => 1,
                            'maximum'     => 20,
                        ),
                        'include_settings' => array(
                            'type'        => 'boolean',
                            'description' => __( 'Whether to include widget settings. Default: true', 'hostinger-ai-assistant' ),
                            'default'     => true,
                        ),
                        'css_class_filter' => array(
                            'type'        => 'string',
                            'description' => __( 'Optional CSS class to filter by (e.g., "hostinger-ai-*")', 'hostinger-ai-assistant' ),
                            'default'     => '',
                        ),
                    ),
                    'required'   => array( 'post_id', 'widget_types' ),
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
                        'title'         => 'Find Widgets',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    public function execute( array $input ): array {
        $post_id = $input['post_id'];

        $query = new WidgetSearchQuery(
            $input['widget_types'],
            $input['max_depth'] ?? 10,
            $input['include_settings'] ?? true,
            $input['css_class_filter'] ?? ''
        );

        $result = $this->get_elementor_data( $post_id );
        if ( isset( $result['error_code'] ) ) {
            return $result;
        }

        $elementor_data = $result['data'];
        $found_widgets  = array();
        $this->search_widgets( $elementor_data, $query, $found_widgets );

        return array(
            'post_id'       => $post_id,
            'widgets_found' => count( $found_widgets ),
            'widgets'       => $found_widgets,
        );
    }
}
