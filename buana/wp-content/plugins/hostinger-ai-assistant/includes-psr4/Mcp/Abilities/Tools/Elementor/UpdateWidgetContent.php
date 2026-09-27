<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class UpdateWidgetContent extends BaseElementorTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-update-widget-content',
            array(
                'label'               => __( 'Update Elementor Widget Content', 'hostinger-ai-assistant' ),
                'description'         => __( 'Safely updates text content of Elementor widgets (heading, button, text-editor). Updates text/title/editor fields while preserving all other settings.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_id'   => array(
                            'type'        => 'integer',
                            'description' => __( 'The ID of the post/page containing the widget', 'hostinger-ai-assistant' ),
                        ),
                        'widget_id' => array(
                            'type'        => 'string',
                            'description' => __( 'The unique ID of the widget to update', 'hostinger-ai-assistant' ),
                        ),
                        'content'   => array(
                            'type'        => 'string',
                            'description' => __( 'The new content text', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'post_id', 'widget_id', 'content' ),
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
                        'title'           => 'Update Widget Content',
                        'readOnlyHint'    => false,
                        'destructiveHint' => false,
                        'idempotentHint'  => true,
                        'openWorldHint'   => false,
                    ),
                ),
            )
        );
    }

    public function execute( array $input ): array {
        $post_id   = $input['post_id'];
        $widget_id = $input['widget_id'];
        $content   = $input['content'];

        $result = $this->get_elementor_data( $post_id );
        if ( isset( $result['error_code'] ) ) {
            return $result;
        }

        $elementor_data = $result['data'];

        $updated = $this->set_content_field( $elementor_data, $widget_id, $content, $old_content );

        if ( ! $updated ) {
            return array(
                'success'    => false,
                'error_code' => 'WIDGET_NOT_FOUND',
                /* translators: %s: widget id  */
                'message'    => sprintf( __( "Widget with ID '%s' not found", 'hostinger-ai-assistant' ), $widget_id ),
            );
        }

        $save_result = $this->save_elementor_data( $post_id, $elementor_data );
        if ( ! $save_result['success'] ) {
            return $save_result;
        }

        return array(
            'success'     => true,
            'widget_id'   => $widget_id,
            'old_content' => $old_content,
            'new_content' => $content,
            'post_id'     => $post_id,
        );
    }

    private function set_content_field( array &$elementor_data, string $widget_id, string $content, &$old_content ): bool {
        return $this->update_widget_in_tree(
            $elementor_data,
            $widget_id,
            fn( &$widget ) => $this->apply_content_to_widget( $widget, $content, $old_content )
        );
    }

    private function apply_content_to_widget( array &$widget, string $content, &$old_content ) {
        $widget_type = $widget['widgetType'] ?? '';
        $allowed     = array( 'heading', 'button', 'text-editor' );

        if ( ! in_array( $widget_type, $allowed, true ) ) {
            return array(
                'error_code' => 'UNSUPPORTED_WIDGET_TYPE',
                /* translators: %s: widget type  */
                'message'    => sprintf( __( "Widget type '%s' does not support content updates", 'hostinger-ai-assistant' ), $widget_type ),
            );
        }

        $content_field = match ( $widget_type ) {
            'heading' => 'title',
            'button' => 'text',
            'text-editor' => 'editor',
            default => null,
        };

        if ( ! $content_field ) {
            return array(
                'error_code' => 'NO_CONTENT_FIELD',
                'message'    => __( 'Could not determine content field for widget type', 'hostinger-ai-assistant' ),
            );
        }

        $old_content                          = $widget['settings'][ $content_field ] ?? '';
        $widget['settings'][ $content_field ] = $content;

        return null;
    }
}
