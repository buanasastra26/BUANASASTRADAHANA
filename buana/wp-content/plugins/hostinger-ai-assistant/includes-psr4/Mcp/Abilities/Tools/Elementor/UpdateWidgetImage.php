<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class UpdateWidgetImage extends BaseElementorTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-update-widget-image',
            array(
                'label'               => __( 'Update Elementor Widget Image', 'hostinger-ai-assistant' ),
                'description'         => __( 'Updates an Elementor image widget source. IMPORTANT: only use an image_url that is publicly reachable and returns HTTP 200 - never a placeholder or hallucinated URL, as this causes broken (404) images. Do not invent image URLs. Never hotlink an external image straight into a widget, because external URLs can expire and break. To use an image from the web, first sideload it with media-upload-from-url, then pass the returned attachment id as image_id and its url as image_url. To reuse an existing image, find it with media-search or media-list and pass its image_id.', 'hostinger-ai-assistant' ),
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
                            'description' => __( 'The unique ID of the image widget to update', 'hostinger-ai-assistant' ),
                        ),
                        'image_url' => array(
                            'type'        => 'string',
                            'description' => __( 'The new image URL. Must be a real, currently reachable URL that returns HTTP 200 (not a 404, placeholder, or made-up URL). Use a WordPress media library URL - sideload external images with media-upload-from-url first to avoid broken images.', 'hostinger-ai-assistant' ),
                        ),
                        'image_id'  => array(
                            'type'        => 'integer',
                            'description' => __( 'Optional but recommended: WordPress media library attachment ID (from media-search or media-list). Using a media library image is the most reliable way to avoid 404 images. When provided, it should match image_url.', 'hostinger-ai-assistant' ),
                        ),
                        'alt_text'  => array(
                            'type'        => 'string',
                            'description' => __( 'Optional: Image alt text for accessibility', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'post_id', 'widget_id', 'image_url' ),
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
                        'title'           => 'Update Widget Image',
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
        $image_url = $input['image_url'];
        $image_id  = $input['image_id'] ?? null;
        $alt_text  = $input['alt_text'] ?? null;

        $result = $this->get_elementor_data( $post_id );
        if ( isset( $result['error_code'] ) ) {
            return $result;
        }

        $elementor_data = $result['data'];

        $updated = false;
        $old_url = '';

        $this->update_widget_in_tree(
            $elementor_data,
            $widget_id,
            function ( &$widget ) use ( $image_url, $image_id, $alt_text, &$updated, &$old_url ) {
                $widget_type = $widget['widgetType'] ?? '';
                if ( $widget_type !== 'image' ) {
                    return array(
                        'error_code' => 'NOT_IMAGE_WIDGET',
                        'message'    => "Widget is type '{$widget_type}', not 'image'",
                    );
                }

                $old_url = $widget['settings']['image']['url'] ?? '';

                if ( ! isset( $widget['settings']['image'] ) ) {
                    $widget['settings']['image'] = array();
                }

                $widget['settings']['image']['url'] = $image_url;

                if ( $image_id !== null ) {
                    $widget['settings']['image']['id'] = $image_id;
                }

                if ( $alt_text !== null ) {
                    $widget['settings']['image_alt'] = $alt_text;
                }

                $updated = true;

                return null;
            }
        );

        if ( ! $updated ) {
            return array(
                'success'    => false,
                'error_code' => 'WIDGET_NOT_FOUND',
                /* translators: %s: widget id  */
                'message'    => __( 'Widget with ID %s not found', 'hostinger-ai-assistant' ),
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
            'new_url'   => $image_url,
        );
    }
}
