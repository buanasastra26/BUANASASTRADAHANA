<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class UpdateWidgetStyles extends BaseElementorTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-update-widget-styles',
            array(
                'label'               => __( 'Update Elementor Widget Styles', 'hostinger-ai-assistant' ),
                'description'         => __( 'Updates visual styles of Elementor widgets including colors, typography, spacing (padding/margin), borders, and alignment. Supports both direct values and responsive settings.', 'hostinger-ai-assistant' ),
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
                        'styles'    => array(
                            'type'        => 'object',
                            'description' => __( 'Style properties to update. All properties are optional. Only provide the properties you want to change.', 'hostinger-ai-assistant' ),
                            'properties'  => array(
                                'colors'     => array(
                                    'type'        => 'object',
                                    'description' => __( 'Color properties. Values should be valid CSS colors (hex, rgb, rgba). Example: "#FF0000" or "rgb(255,0,0)"', 'hostinger-ai-assistant' ),
                                    'properties'  => array(
                                        'text_color'              => array(
                                            'type'        => 'string',
                                            'description' => __( 'Text color. Used for text elements.', 'hostinger-ai-assistant' ),
                                        ),
                                        'title_color'             => array(
                                            'type'        => 'string',
                                            'description' => __( 'Title/heading color. Used for headings and titles.', 'hostinger-ai-assistant' ),
                                        ),
                                        'background_color'        => array(
                                            'type'        => 'string',
                                            'description' => __( 'Background color', 'hostinger-ai-assistant' ),
                                        ),
                                        'border_color'            => array(
                                            'type'        => 'string',
                                            'description' => __( 'Border color', 'hostinger-ai-assistant' ),
                                        ),
                                        'button_text_color'       => array(
                                            'type'        => 'string',
                                            'description' => __( 'Button text color', 'hostinger-ai-assistant' ),
                                        ),
                                        'button_background_color' => array(
                                            'type'        => 'string',
                                            'description' => __( 'Button background color', 'hostinger-ai-assistant' ),
                                        ),
                                        'hover_color'             => array(
                                            'type'        => 'string',
                                            'description' => __( 'Hover state color', 'hostinger-ai-assistant' ),
                                        ),
                                        'icon_color'              => array(
                                            'type'        => 'string',
                                            'description' => __( 'Icon color', 'hostinger-ai-assistant' ),
                                        ),
                                    ),
                                ),
                                'typography' => array(
                                    'type'        => 'object',
                                    'description' => __( 'Typography settings for text appearance', 'hostinger-ai-assistant' ),
                                    'properties'  => array(
                                        'font_family'     => array(
                                            'type'        => 'string',
                                            'description' => __( 'Font family name. Example: "Arial", "Roboto"', 'hostinger-ai-assistant' ),
                                        ),
                                        'font_size'       => array(
                                            'type'        => 'object',
                                            'description' => __( 'Font size with unit', 'hostinger-ai-assistant' ),
                                            'properties'  => array(
                                                'size' => array(
                                                    'type'        => 'number',
                                                    'description' => __( 'Size value', 'hostinger-ai-assistant' ),
                                                ),
                                                'unit' => array(
                                                    'type'        => 'string',
                                                    'description' => __( 'Unit: px, em, rem, %', 'hostinger-ai-assistant' ),
                                                    'enum'        => array( 'px', 'em', 'rem', '%', 'vw' ),
                                                    'default'     => 'px',
                                                ),
                                            ),
                                        ),
                                        'font_weight'     => array(
                                            'type'        => 'string',
                                            'description' => __( 'Font weight. Example: "400", "700", "bold", "normal"', 'hostinger-ai-assistant' ),
                                            'enum'        => array(
                                                '100',
                                                '200',
                                                '300',
                                                '400',
                                                '500',
                                                '600',
                                                '700',
                                                '800',
                                                '900',
                                                'normal',
                                                'bold',
                                            ),
                                        ),
                                        'line_height'     => array(
                                            'type'        => 'object',
                                            'description' => __( 'Line height with unit', 'hostinger-ai-assistant' ),
                                            'properties'  => array(
                                                'size' => array(
                                                    'type'        => 'number',
                                                    'description' => __( 'Size value', 'hostinger-ai-assistant' ),
                                                ),
                                                'unit' => array(
                                                    'type'        => 'string',
                                                    'description' => __( 'Unit: px, em, rem, or empty for unitless', 'hostinger-ai-assistant' ),
                                                    'enum'        => array( 'px', 'em', 'rem', '' ),
                                                    'default'     => '',
                                                ),
                                            ),
                                        ),
                                        'letter_spacing'  => array(
                                            'type'        => 'object',
                                            'description' => __( 'Letter spacing with unit', 'hostinger-ai-assistant' ),
                                            'properties'  => array(
                                                'size' => array(
                                                    'type'        => 'number',
                                                    'description' => __( 'Size value', 'hostinger-ai-assistant' ),
                                                ),
                                                'unit' => array(
                                                    'type'        => 'string',
                                                    'description' => __( 'Unit: px, em', 'hostinger-ai-assistant' ),
                                                    'enum'        => array( 'px', 'em' ),
                                                    'default'     => 'px',
                                                ),
                                            ),
                                        ),
                                        'text_transform'  => array(
                                            'type'        => 'string',
                                            'description' => __( 'Text transformation', 'hostinger-ai-assistant' ),
                                            'enum'        => array( 'none', 'uppercase', 'lowercase', 'capitalize' ),
                                        ),
                                        'font_style'      => array(
                                            'type'        => 'string',
                                            'description' => __( 'Font style', 'hostinger-ai-assistant' ),
                                            'enum'        => array( 'normal', 'italic', 'oblique' ),
                                        ),
                                        'text_decoration' => array(
                                            'type'        => 'string',
                                            'description' => __( 'Text decoration', 'hostinger-ai-assistant' ),
                                            'enum'        => array( 'none', 'underline', 'overline', 'line-through' ),
                                        ),
                                    ),
                                ),
                                'padding'    => array(
                                    'type'        => 'object',
                                    'description' => __( 'Padding spacing. Set "reset" to true to remove padding, or provide dimension values.', 'hostinger-ai-assistant' ),
                                    'properties'  => array(
                                        'reset'    => array(
                                            'type'        => 'boolean',
                                            'description' => __( 'Set to true to remove padding entirely', 'hostinger-ai-assistant' ),
                                            'default'     => false,
                                        ),
                                        'top'      => array(
                                            'type'        => 'string',
                                            'description' => __( 'Top padding value (as string)', 'hostinger-ai-assistant' ),
                                        ),
                                        'right'    => array(
                                            'type'        => 'string',
                                            'description' => __( 'Right padding value (as string)', 'hostinger-ai-assistant' ),
                                        ),
                                        'bottom'   => array(
                                            'type'        => 'string',
                                            'description' => __( 'Bottom padding value (as string)', 'hostinger-ai-assistant' ),
                                        ),
                                        'left'     => array(
                                            'type'        => 'string',
                                            'description' => __( 'Left padding value (as string)', 'hostinger-ai-assistant' ),
                                        ),
                                        'unit'     => array(
                                            'type'        => 'string',
                                            'description' => __( 'Unit for all padding values', 'hostinger-ai-assistant' ),
                                            'enum'        => array( 'px', 'em', 'rem', '%', 'vw', 'vh' ),
                                            'default'     => 'px',
                                        ),
                                        'isLinked' => array(
                                            'type'        => 'boolean',
                                            'description' => __( 'Whether all sides are linked together', 'hostinger-ai-assistant' ),
                                            'default'     => true,
                                        ),
                                    ),
                                ),
                                'margin'     => array(
                                    'type'        => 'object',
                                    'description' => __( 'Margin spacing. Set "reset" to true to remove margin, or provide dimension values.', 'hostinger-ai-assistant' ),
                                    'properties'  => array(
                                        'reset'    => array(
                                            'type'        => 'boolean',
                                            'description' => __( 'Set to true to remove margin entirely', 'hostinger-ai-assistant' ),
                                            'default'     => false,
                                        ),
                                        'top'      => array(
                                            'type'        => 'string',
                                            'description' => __( 'Top margin value (as string)', 'hostinger-ai-assistant' ),
                                        ),
                                        'right'    => array(
                                            'type'        => 'string',
                                            'description' => __( 'Right margin value (as string)', 'hostinger-ai-assistant' ),
                                        ),
                                        'bottom'   => array(
                                            'type'        => 'string',
                                            'description' => __( 'Bottom margin value (as string)', 'hostinger-ai-assistant' ),
                                        ),
                                        'left'     => array(
                                            'type'        => 'string',
                                            'description' => __( 'Left margin value (as string)', 'hostinger-ai-assistant' ),
                                        ),
                                        'unit'     => array(
                                            'type'        => 'string',
                                            'description' => __( 'Unit for all margin values', 'hostinger-ai-assistant' ),
                                            'enum'        => array( 'px', 'em', 'rem', '%', 'vw', 'vh' ),
                                            'default'     => 'px',
                                        ),
                                        'isLinked' => array(
                                            'type'        => 'boolean',
                                            'description' => __( 'Whether all sides are linked together', 'hostinger-ai-assistant' ),
                                            'default'     => true,
                                        ),
                                    ),
                                ),
                                'border'     => array(
                                    'type'        => 'object',
                                    'description' => __( 'Border styling', 'hostinger-ai-assistant' ),
                                    'properties'  => array(
                                        'type'   => array(
                                            'type'        => 'string',
                                            'description' => __( 'Border style type', 'hostinger-ai-assistant' ),
                                            'enum'        => array(
                                                'none',
                                                'solid',
                                                'double',
                                                'dotted',
                                                'dashed',
                                                'groove',
                                            ),
                                        ),
                                        'width'  => array(
                                            'type'        => 'object',
                                            'description' => __( 'Border width for each side', 'hostinger-ai-assistant' ),
                                            'properties'  => array(
                                                'top'      => array(
                                                    'type'        => 'string',
                                                    'description' => __( 'Top border width (as string)', 'hostinger-ai-assistant' ),
                                                ),
                                                'right'    => array(
                                                    'type'        => 'string',
                                                    'description' => __( 'Right border width (as string)', 'hostinger-ai-assistant' ),
                                                ),
                                                'bottom'   => array(
                                                    'type'        => 'string',
                                                    'description' => __( 'Bottom border width (as string)', 'hostinger-ai-assistant' ),
                                                ),
                                                'left'     => array(
                                                    'type'        => 'string',
                                                    'description' => __( 'Left border width (as string)', 'hostinger-ai-assistant' ),
                                                ),
                                                'unit'     => array(
                                                    'type'        => 'string',
                                                    'description' => __( 'Unit for border width', 'hostinger-ai-assistant' ),
                                                    'enum'        => array( 'px', 'em', 'rem' ),
                                                    'default'     => 'px',
                                                ),
                                                'isLinked' => array(
                                                    'type'        => 'boolean',
                                                    'description' => __( 'Whether all sides are linked', 'hostinger-ai-assistant' ),
                                                    'default'     => true,
                                                ),
                                            ),
                                        ),
                                        'color'  => array(
                                            'type'        => 'string',
                                            'description' => __( 'Border color (hex, rgb, rgba)', 'hostinger-ai-assistant' ),
                                        ),
                                        'radius' => array(
                                            'type'        => 'object',
                                            'description' => __( 'Border radius for corners', 'hostinger-ai-assistant' ),
                                            'properties'  => array(
                                                'top'      => array(
                                                    'type'        => 'string',
                                                    'description' => __( 'Top-left border radius (as string)', 'hostinger-ai-assistant' ),
                                                ),
                                                'right'    => array(
                                                    'type'        => 'string',
                                                    'description' => __( 'Top-right border radius (as string)', 'hostinger-ai-assistant' ),
                                                ),
                                                'bottom'   => array(
                                                    'type'        => 'string',
                                                    'description' => __( 'Bottom-right border radius (as string)', 'hostinger-ai-assistant' ),
                                                ),
                                                'left'     => array(
                                                    'type'        => 'string',
                                                    'description' => __( 'Bottom-left border radius (as string)', 'hostinger-ai-assistant' ),
                                                ),
                                                'unit'     => array(
                                                    'type'        => 'string',
                                                    'description' => __( 'Unit for border radius', 'hostinger-ai-assistant' ),
                                                    'enum'        => array( 'px', 'em', 'rem', '%' ),
                                                    'default'     => 'px',
                                                ),
                                                'isLinked' => array(
                                                    'type'        => 'boolean',
                                                    'description' => __( 'Whether all corners are linked', 'hostinger-ai-assistant' ),
                                                    'default'     => true,
                                                ),
                                            ),
                                        ),
                                    ),
                                ),
                                'alignment'  => array(
                                    'type'        => 'object',
                                    'description' => __( 'Element alignment settings', 'hostinger-ai-assistant' ),
                                    'properties'  => array(
                                        'align'      => array(
                                            'type'        => 'string',
                                            'description' => __( 'Horizontal alignment', 'hostinger-ai-assistant' ),
                                            'enum'        => array( 'left', 'center', 'right', 'justify' ),
                                        ),
                                        'text_align' => array(
                                            'type'        => 'string',
                                            'description' => __( 'Text alignment', 'hostinger-ai-assistant' ),
                                            'enum'        => array( 'left', 'center', 'right', 'justify' ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                    'required'   => array( 'post_id', 'widget_id', 'styles' ),
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
                        'title'           => 'Update Widget Styles',
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
        $styles    = $input['styles'] ?? array();

        if ( empty( $styles ) ) {
            return array(
                'success'    => false,
                'error_code' => 'NO_STYLES_PROVIDED',
                'message'    => 'No style properties provided to update',
            );
        }

        $result = $this->get_elementor_data( $post_id );
        if ( isset( $result['error_code'] ) ) {
            return $result;
        }

        $elementor_data = $result['data'];

        $updated_styles  = array();
        $previous_values = array();

        $updated = $this->set_styles( $elementor_data, $widget_id, $styles, $updated_styles, $previous_values );

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
            'post_id'         => $post_id,
            'updated_styles'  => $updated_styles,
            'previous_values' => $previous_values,
        );
    }

    private function set_styles( array &$elementor_data, string $widget_id, array $styles, array &$updated_styles, array &$previous_values ): bool {
        return $this->update_widget_in_tree(
            $elementor_data,
            $widget_id,
            function ( &$widget ) use ( $styles, &$updated_styles, &$previous_values ) {
                if ( ! isset( $widget['settings'] ) ) {
                    $widget['settings'] = array();
                }

                $this->set_color_styles( $widget, $styles, $updated_styles, $previous_values );
                $this->set_typography_styles( $widget, $styles, $updated_styles, $previous_values );
                $this->set_padding_style( $widget, $styles, $updated_styles, $previous_values );
                $this->set_margin_style( $widget, $styles, $updated_styles, $previous_values );
                $this->set_border_style( $widget, $styles, $updated_styles, $previous_values );
                $this->set_alignment_style( $widget, $styles, $updated_styles, $previous_values );

                return null;
            }
        );
    }

    private function set_color_styles( array &$widget, array $styles, array &$updated_styles, array &$previous_values ): void {
        if ( isset( $styles['colors'] ) && is_array( $styles['colors'] ) ) {
            foreach ( $styles['colors'] as $color_prop => $color_value ) {
                if ( ! empty( $color_value ) ) {
                    $previous_values[ $color_prop ]          = $widget['settings'][ $color_prop ] ?? null;
                    $widget['settings'][ $color_prop ]       = $color_value;
                    $updated_styles['colors'][ $color_prop ] = $color_value;

                    if ( isset( $widget['settings']['__globals__'][ $color_prop ] ) ) {
                        unset( $widget['settings']['__globals__'][ $color_prop ] );
                    }
                }
            }
        }
    }

    private function set_typography_styles( array &$widget, array $styles, array &$updated_styles, array &$previous_values ): void {
        if ( isset( $styles['typography'] ) && is_array( $styles['typography'] ) ) {
            $typography_settings = $styles['typography'];

            $has_typography_changes = false;

            foreach ( $typography_settings as $typo_prop => $typo_value ) {
                $setting_key = "typography_{$typo_prop}";

                $previous_values[ $setting_key ] = $widget['settings'][ $setting_key ] ?? null;

                if ( $typo_prop === 'font_family' ) {
                    $widget['settings'][ $setting_key ]         = $typo_value;
                    $updated_styles['typography'][ $typo_prop ] = $typo_value;
                    $has_typography_changes                     = true;
                } elseif ( in_array(
                    $typo_prop,
                    array(
                        'font_weight',
                        'text_transform',
                        'font_style',
                        'text_decoration',
                    ),
                    true
                ) ) {
                    $widget['settings'][ $setting_key ]         = $typo_value;
                    $updated_styles['typography'][ $typo_prop ] = $typo_value;
                    $has_typography_changes                     = true;
                } elseif ( in_array( $typo_prop, array( 'font_size', 'line_height', 'letter_spacing' ), true ) && is_array( $typo_value ) ) {
                    $widget['settings'][ $setting_key ]         = $typo_value;
                    $updated_styles['typography'][ $typo_prop ] = $typo_value;
                    $has_typography_changes                     = true;
                }
            }

            if ( $has_typography_changes && isset( $widget['settings']['__globals__']['typography_typography'] ) ) {
                unset( $widget['settings']['__globals__']['typography_typography'] );
            }
        }
    }

    private function set_padding_style( array &$widget, array $styles, array &$updated_styles, array &$previous_values ): void {
        if ( isset( $styles['padding'] ) && is_array( $styles['padding'] ) ) {
            $padding_settings = $styles['padding'];

            if ( isset( $padding_settings['reset'] ) && $padding_settings['reset'] === true ) {
                $previous_values['_padding'] = $widget['settings']['_padding'] ?? null;
                unset( $widget['settings']['_padding'] );
                $updated_styles['padding'] = 'reset';
            } else {
                $padding_data = array();

                foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
                    if ( isset( $padding_settings[ $side ] ) ) {
                        $padding_data[ $side ] = $padding_settings[ $side ];
                    }
                }

                if ( ! empty( $padding_data ) ) {
                    if ( isset( $padding_settings['unit'] ) ) {
                        $padding_data['unit'] = $padding_settings['unit'];
                    }
                    if ( isset( $padding_settings['isLinked'] ) ) {
                        $padding_data['isLinked'] = $padding_settings['isLinked'];
                    }

                    $previous_values['_padding']    = $widget['settings']['_padding'] ?? null;
                    $widget['settings']['_padding'] = $padding_data;
                    $updated_styles['padding']      = $padding_data;
                }
            }
        }
    }

    private function set_margin_style( array &$widget, array $styles, array &$updated_styles, array &$previous_values ): void {
        if ( isset( $styles['margin'] ) && is_array( $styles['margin'] ) ) {
            $margin_settings = $styles['margin'];

            if ( isset( $margin_settings['reset'] ) && $margin_settings['reset'] === true ) {
                $previous_values['_margin'] = $widget['settings']['_margin'] ?? null;
                unset( $widget['settings']['_margin'] );
                $updated_styles['margin'] = 'reset';
            } else {
                $margin_data = array();

                foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
                    if ( isset( $margin_settings[ $side ] ) ) {
                        $margin_data[ $side ] = $margin_settings[ $side ];
                    }
                }

                if ( ! empty( $margin_data ) ) {
                    if ( isset( $margin_settings['unit'] ) ) {
                        $margin_data['unit'] = $margin_settings['unit'];
                    }
                    if ( isset( $margin_settings['isLinked'] ) ) {
                        $margin_data['isLinked'] = $margin_settings['isLinked'];
                    }

                    $previous_values['_margin']    = $widget['settings']['_margin'] ?? null;
                    $widget['settings']['_margin'] = $margin_data;
                    $updated_styles['margin']      = $margin_data;
                }
            }
        }
    }

    private function set_border_style( array &$widget, array $styles, array &$updated_styles, array &$previous_values ): void {
        if ( isset( $styles['border'] ) && is_array( $styles['border'] ) ) {
            $border_settings = $styles['border'];

            if ( isset( $border_settings['type'] ) ) {
                $previous_values['_border_border']    = $widget['settings']['_border_border'] ?? null;
                $widget['settings']['_border_border'] = $border_settings['type'];
                $updated_styles['border']['type']     = $border_settings['type'];
            }

            if ( isset( $border_settings['width'] ) && is_array( $border_settings['width'] ) ) {
                $width_data = array();
                foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
                    if ( isset( $border_settings['width'][ $side ] ) ) {
                        $width_data[ $side ] = $border_settings['width'][ $side ];
                    }
                }

                if ( ! empty( $width_data ) ) {
                    if ( isset( $border_settings['width']['unit'] ) ) {
                        $width_data['unit'] = $border_settings['width']['unit'];
                    }
                    if ( isset( $border_settings['width']['isLinked'] ) ) {
                        $width_data['isLinked'] = $border_settings['width']['isLinked'];
                    }

                    $previous_values['_border_width']    = $widget['settings']['_border_width'] ?? null;
                    $widget['settings']['_border_width'] = $width_data;
                    $updated_styles['border']['width']   = $width_data;
                }
            }

            if ( isset( $border_settings['color'] ) ) {
                $previous_values['_border_color']    = $widget['settings']['_border_color'] ?? null;
                $widget['settings']['_border_color'] = $border_settings['color'];
                $updated_styles['border']['color']   = $border_settings['color'];
            }

            if ( isset( $border_settings['radius'] ) && is_array( $border_settings['radius'] ) ) {
                $radius_data = array();
                foreach ( array( 'top', 'right', 'bottom', 'left' ) as $corner ) {
                    if ( isset( $border_settings['radius'][ $corner ] ) ) {
                        $radius_data[ $corner ] = $border_settings['radius'][ $corner ];
                    }
                }

                if ( ! empty( $radius_data ) ) {
                    if ( isset( $border_settings['radius']['unit'] ) ) {
                        $radius_data['unit'] = $border_settings['radius']['unit'];
                    }
                    if ( isset( $border_settings['radius']['isLinked'] ) ) {
                        $radius_data['isLinked'] = $border_settings['radius']['isLinked'];
                    }

                    $previous_values['_border_radius']    = $widget['settings']['_border_radius'] ?? null;
                    $widget['settings']['_border_radius'] = $radius_data;
                    $updated_styles['border']['radius']   = $radius_data;
                }
            }
        }
    }

    private function set_alignment_style( array &$widget, array $styles, array &$updated_styles, array &$previous_values ): void {
        if ( isset( $styles['alignment'] ) && is_array( $styles['alignment'] ) ) {
            if ( isset( $styles['alignment']['align'] ) ) {
                $previous_values['align']             = $widget['settings']['align'] ?? null;
                $widget['settings']['align']          = $styles['alignment']['align'];
                $updated_styles['alignment']['align'] = $styles['alignment']['align'];
            }

            if ( isset( $styles['alignment']['text_align'] ) ) {
                $previous_values['text_align']             = $widget['settings']['text_align'] ?? null;
                $widget['settings']['text_align']          = $styles['alignment']['text_align'];
                $updated_styles['alignment']['text_align'] = $styles['alignment']['text_align'];
            }
        }
    }
}
