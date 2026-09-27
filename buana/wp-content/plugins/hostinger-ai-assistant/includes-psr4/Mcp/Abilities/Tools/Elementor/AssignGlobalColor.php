<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class AssignGlobalColor extends BaseElementorTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-assign-global-color',
            array(
                'label'               => __( 'Assign Elementor Global Color', 'hostinger-ai-assistant' ),
                'description'         => __( 'Assigns Elementor global colors to widget properties. First use get_active_kit to see available global colors and their IDs. Common color properties: title_color (heading color), text_color (paragraph color), background_color, button_background_color, button_text_color, border_color, hover_color. This creates a dynamic link - when the global color changes in the kit, all widgets using it update automatically.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_id'         => array(
                            'type'        => 'integer',
                            'description' => __( 'The ID of the post/page containing the widget', 'hostinger-ai-assistant' ),
                        ),
                        'widget_id'       => array(
                            'type'        => 'string',
                            'description' => __( 'The unique ID of the widget to update', 'hostinger-ai-assistant' ),
                        ),
                        'property'        => array(
                            'type'        => 'string',
                            'description' => __( 'The color property to assign. Common properties: title_color (for headings), text_color (for text), background_color, button_background_color, button_text_color, border_color, hover_color, icon_color', 'hostinger-ai-assistant' ),
                            'enum'        => array(
                                'title_color',
                                'text_color',
                                'background_color',
                                'button_background_color',
                                'button_text_color',
                                'border_color',
                                'hover_color',
                                'icon_color',
                                'description_color',
                                'heading_color',
                            ),
                        ),
                        'global_color_id' => array(
                            'type'        => 'string',
                            'description' => __( 'The global color ID from the active kit. Use get_active_kit tool first to see available color IDs. Common system colors: primary, secondary, text, accent. Custom colors have unique IDs like "09cc561".', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'post_id', 'widget_id', 'property', 'global_color_id' ),
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
                        'title'           => 'Assign Global Color',
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
        $post_id         = $input['post_id'];
        $widget_id       = $input['widget_id'];
        $property        = $input['property'];
        $global_color_id = $input['global_color_id'];

        $kit_result = $this->get_active_kit_colors();
        if ( isset( $kit_result['error_code'] ) ) {
            return $kit_result;
        }

        $system_colors = $kit_result['colors']['system'] ?? array();
        $custom_colors = $kit_result['colors']['custom'] ?? array();
        $colors        = array_merge( $system_colors, $custom_colors );
        $color_ids     = array_column( $colors, 'id' );
        $color_exists  = in_array( $global_color_id, $color_ids, true );

        if ( ! $color_exists ) {
            return array(
                'success'    => false,
                'error_code' => 'COLOR_NOT_FOUND',
                /* translators: %s: global color id  */
                'message'    => sprintf( __( "Global color with ID '%s' not found in active kit", 'hostinger-ai-assistant' ), $global_color_id ),
            );
        }

        $result = $this->get_elementor_data( $post_id );
        if ( isset( $result['error_code'] ) ) {
            return $result;
        }

        $elementor_data = $result['data'];

        $updated = $this->set_global_color_property( $elementor_data, $widget_id, $property, $global_color_id, $previous_value );

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
            'success'         => true,
            'widget_id'       => $widget_id,
            'property'        => $property,
            'global_color_id' => $global_color_id,
            'previous_value'  => $previous_value,
        );
    }

    private function set_global_color_property( array &$elementor_data, string $widget_id, string $property, string $global_color_id, &$previous_value ): bool {
        return $this->update_widget_in_tree(
            $elementor_data,
            $widget_id,
            fn( &$widget ) => $this->apply_global_color_to_widget( $widget, $property, $global_color_id, $previous_value )
        );
    }

    private function apply_global_color_to_widget( array &$widget, string $property, string $global_color_id, &$previous_value ): void {
        $previous_value = $widget['settings'][ $property ] ?? null;

        $global_settings                   = $widget['settings']['__globals__'] ?? array();
        $global_settings[ $property ]      = "globals/colors?id={$global_color_id}";
        $widget['settings']['__globals__'] = $global_settings;

        unset( $widget['settings'][ $property ] );
    }

    private function get_active_kit_colors(): array {
        $active_kit_id = get_option( 'elementor_active_kit' );

        if ( ! $active_kit_id ) {
            return array(
                'success'    => false,
                'error_code' => 'NO_ACTIVE_KIT',
                'message'    => __( 'No active Elementor kit found', 'hostinger-ai-assistant' ),
            );
        }

        $kit_settings = get_post_meta( $active_kit_id, '_elementor_page_settings', true );
        if ( ! is_array( $kit_settings ) ) {
            $kit_settings = array();
        }

        return array( 'colors' => $this->extract_kit_colors( $kit_settings ) );
    }
}
