<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class UpdateWidgetLink extends BaseElementorTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-update-widget-link',
            array(
                'label'               => __( 'Update Elementor Widget Link', 'hostinger-ai-assistant' ),
                'description'         => __( 'Updates link URLs in buttons and other link-capable Elementor widgets. Supports external and nofollow attributes.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_id'     => array(
                            'type'        => 'integer',
                            'description' => __( 'The ID of the post/page containing the widget', 'hostinger-ai-assistant' ),
                        ),
                        'widget_id'   => array(
                            'type'        => 'string',
                            'description' => __( 'The unique ID of the widget to update', 'hostinger-ai-assistant' ),
                        ),
                        'url'         => array(
                            'type'        => 'string',
                            'description' => __( 'The new link URL', 'hostinger-ai-assistant' ),
                        ),
                        'is_external' => array(
                            'type'        => 'boolean',
                            'description' => __( 'Optional: Open link in new tab', 'hostinger-ai-assistant' ),
                            'default'     => false,
                        ),
                        'nofollow'    => array(
                            'type'        => 'boolean',
                            'description' => __( 'Optional: Add nofollow attribute', 'hostinger-ai-assistant' ),
                            'default'     => false,
                        ),
                    ),
                    'required'   => array( 'post_id', 'widget_id', 'url' ),
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
                        'title'           => 'Update Widget Link',
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
        $post_id     = $input['post_id'];
        $widget_id   = $input['widget_id'];
        $url         = $input['url'];
        $is_external = $input['is_external'] ?? false;
        $nofollow    = $input['nofollow'] ?? false;

        $result = $this->get_elementor_data( $post_id );
        if ( isset( $result['error_code'] ) ) {
            return $result;
        }

        $elementor_data = $result['data'];

        $updated = false;
        $old_url = '';

        try {
            $this->update_widget_in_tree(
                $elementor_data,
                $widget_id,
                function ( &$widget ) use ( $url, $is_external, $nofollow, &$updated, &$old_url ) {
                    $widget_type = $widget['widgetType'] ?? '';

                    if ( $widget_type !== 'button' ) {
                        throw new Exception( __( 'Update links is only supported for button widgets', 'hostinger-ai-assistant' ) );
                    }

                    $link_field = 'link';
                    if ( isset( $widget['settings'][ $link_field ] ) && is_array( $widget['settings'][ $link_field ] ) ) {
                        $old_url = $widget['settings'][ $link_field ]['url'] ?? '';
                    } else {
                        $old_url = $widget['settings'][ $link_field ] ?? '';
                    }

                    $widget['settings'][ $link_field ] = array(
                        'url'         => $url,
                        'is_external' => $is_external ? 'on' : '',
                        'nofollow'    => $nofollow ? 'on' : '',
                    );

                    $updated = true;

                    return null;
                }
            );
        } catch ( Exception $e ) {
            return array(
                'success'    => false,
                'error_code' => 'WIDGET_NOT_FOUND',
                'message'    => $e->getMessage(),
            );
        }

        if ( ! $updated ) {
            return array(
                'success'    => false,
                'error_code' => 'WIDGET_NOT_FOUND',
                'message'    => "Widget with ID '{$widget_id}' not found",
            );
        }

        $save_result = $this->save_elementor_data( $post_id, $elementor_data );
        if ( ! $save_result['success'] ) {
            return $save_result;
        }

        return array(
            'success'   => true,
            'widget_id' => $widget_id,
            'old_url'   => $old_url,
            'new_url'   => $url,
        );
    }
}
