<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Elementor: Edit container styles (backgrounds, spacing, borders, effects) with responsive device targeting.
 */
class EditContainer extends ContainerTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-edit-container',
            array(
                'label'               => __( 'Edit Elementor Container Styles', 'hostinger-ai-assistant' ),
                'description'         => __( 'Updates container styling: background (color, image, gradient or video via the "background" object), padding/margin, border (radius/width/color/style), opacity, z-index. Supports responsive device targeting. Setting any background replaces the container\'s existing background — use elementor-get-page-structure to check the current background first so you do not overwrite an existing video/image.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array_merge(
                        $this->schema_post_id(),
                        $this->schema_container_target(),
                        $this->schema_background(),
                        array(
                            'device'           => array(
                                'type'        => 'string',
                                'enum'        => array( 'desktop', 'tablet', 'mobile' ),
                                'default'     => 'desktop',
                                'description' => __( 'Target device for responsive settings', 'hostinger-ai-assistant' ),
                            ),
                            'background_color' => array(
                                'type'        => 'string',
                                'description' => __( 'Shorthand to set a solid color background (equivalent to background.type=color). Warning: this replaces any existing video/image/gradient background on the container with a solid color. For video/image/gradient backgrounds use the "background" object instead.', 'hostinger-ai-assistant' ),
                            ),
                            'padding'          => array(
                                'oneOf'       => array(
                                    array(
                                        'type'    => 'number',
                                        'minimum' => 0,
                                    ),
                                    array(
                                        'type'       => 'object',
                                        'properties' => array(
                                            'top'    => array(
                                                'type'    => 'number',
                                                'minimum' => 0,
                                            ),
                                            'right'  => array(
                                                'type'    => 'number',
                                                'minimum' => 0,
                                            ),
                                            'bottom' => array(
                                                'type'    => 'number',
                                                'minimum' => 0,
                                            ),
                                            'left'   => array(
                                                'type'    => 'number',
                                                'minimum' => 0,
                                            ),
                                            'unit'   => array(
                                                'type'    => 'string',
                                                'enum'    => array( 'px', 'em', 'rem', '%' ),
                                                'default' => 'px',
                                            ),
                                        ),
                                    ),
                                ),
                                'description' => __( 'Padding in px (number for all sides) or object with sides and unit', 'hostinger-ai-assistant' ),
                            ),
                            'margin'           => array(
                                'oneOf'       => array(
                                    array( 'type' => 'number' ),
                                    array(
                                        'type'       => 'object',
                                        'properties' => array(
                                            'top'    => array( 'type' => 'number' ),
                                            'right'  => array( 'type' => 'number' ),
                                            'bottom' => array( 'type' => 'number' ),
                                            'left'   => array( 'type' => 'number' ),
                                            'unit'   => array(
                                                'type'    => 'string',
                                                'enum'    => array( 'px', 'em', 'rem', '%' ),
                                                'default' => 'px',
                                            ),
                                        ),
                                    ),
                                ),
                                'description' => __( 'Margin in px (number for all sides) or object with sides and unit', 'hostinger-ai-assistant' ),
                            ),
                            'border_radius'    => array(
                                'oneOf'       => array(
                                    array(
                                        'type'    => 'number',
                                        'minimum' => 0,
                                    ),
                                    array(
                                        'type'       => 'object',
                                        'properties' => array(
                                            'top'    => array(
                                                'type'    => 'number',
                                                'minimum' => 0,
                                            ),
                                            'right'  => array(
                                                'type'    => 'number',
                                                'minimum' => 0,
                                            ),
                                            'bottom' => array(
                                                'type'    => 'number',
                                                'minimum' => 0,
                                            ),
                                            'left'   => array(
                                                'type'    => 'number',
                                                'minimum' => 0,
                                            ),
                                            'unit'   => array(
                                                'type'    => 'string',
                                                'enum'    => array( 'px', '%' ),
                                                'default' => 'px',
                                            ),
                                        ),
                                    ),
                                ),
                                'description' => __( 'Border radius (number for all corners) or object with corners and unit', 'hostinger-ai-assistant' ),
                            ),
                            'border_width'     => array(
                                'oneOf'       => array(
                                    array(
                                        'type'    => 'number',
                                        'minimum' => 0,
                                    ),
                                    array(
                                        'type'       => 'object',
                                        'properties' => array(
                                            'top'    => array(
                                                'type'    => 'number',
                                                'minimum' => 0,
                                            ),
                                            'right'  => array(
                                                'type'    => 'number',
                                                'minimum' => 0,
                                            ),
                                            'bottom' => array(
                                                'type'    => 'number',
                                                'minimum' => 0,
                                            ),
                                            'left'   => array(
                                                'type'    => 'number',
                                                'minimum' => 0,
                                            ),
                                            'unit'   => array(
                                                'type'    => 'string',
                                                'enum'    => array( 'px' ),
                                                'default' => 'px',
                                            ),
                                        ),
                                    ),
                                ),
                                'description' => __( 'Border width (number for all sides) or object with sides and unit', 'hostinger-ai-assistant' ),
                            ),
                            'border_color'     => array(
                                'type'        => 'string',
                                'description' => __( 'CSS color value for border', 'hostinger-ai-assistant' ),
                            ),
                            'border_style'     => array(
                                'type'        => 'string',
                                'enum'        => array( 'none', 'solid', 'dashed', 'dotted', 'double' ),
                                'description' => __( 'Border style', 'hostinger-ai-assistant' ),
                            ),
                            'opacity'          => array(
                                'type'        => 'number',
                                'minimum'     => 0,
                                'maximum'     => 1,
                                'description' => __( 'Opacity (0..1)', 'hostinger-ai-assistant' ),
                            ),
                            'z_index'          => array(
                                'type'        => 'integer',
                                'description' => __( 'Z-index', 'hostinger-ai-assistant' ),
                            ),
                            'css_class'        => array(
                                'type'        => 'string',
                                'description' => __( 'Optional CSS class to assign/update on the container', 'hostinger-ai-assistant' ),
                            ),
                        )
                    ),
                    'required'   => array( 'post_id', 'container_id' ),
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
                        'title'           => 'Edit Container Styles',
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
        $post_id      = (int) $input['post_id'];
        $container_id = (string) $input['container_id'];

        $result = $this->get_elementor_data( $post_id );
        if ( isset( $result['error_code'] ) ) {
            return $result;
        }

        $elementor_data = $result['data'];

        $applied = array();
        $found   = $this->update_widget_in_tree(
            $elementor_data,
            $container_id,
            function ( &$container ) use ( $input, &$applied ) {
                if ( ( $container['elType'] ?? '' ) !== 'container' ) {
                    return array(
                        'error_code' => 'NOT_A_CONTAINER',
                        /* translators: %s: element id */
                        'message'    => sprintf( __( "Element '%s' is not a container", 'hostinger-ai-assistant' ), $container['id'] ?? '' ),
                    );
                }

                if ( ! isset( $container['settings'] ) || ! is_array( $container['settings'] ) ) {
                    $container['settings'] = array();
                }
                $settings =& $container['settings'];

                $device       = $input['device'] ?? 'desktop';
                $suffix       = $device === 'desktop' ? '' : ( $device === 'tablet' ? '_tablet' : '_mobile' );
                $unit_default = 'px';

                if ( isset( $input['background'] ) && is_array( $input['background'] ) ) {
                    $applied['background'] = $this->apply_background( $settings, $input['background'] );
                } elseif ( isset( $input['background_color'] ) && $input['background_color'] !== '' ) {
                    $settings['background_background']        = 'classic';
                    $settings[ 'background_color' . $suffix ] = $input['background_color'];
                    $applied['background_color']              = $input['background_color'];
                }

                if ( array_key_exists( 'padding', $input ) ) {
                    $pad = $input['padding'];
                    if ( is_numeric( $pad ) ) {
                        $settings[ 'padding' . $suffix ] = array(
                            'unit'   => $unit_default,
                            'top'    => (float) $pad,
                            'right'  => (float) $pad,
                            'bottom' => (float) $pad,
                            'left'   => (float) $pad,
                        );
                    } elseif ( is_array( $pad ) ) {
                        $settings[ 'padding' . $suffix ] = array(
                            'unit'   => $pad['unit'] ?? $unit_default,
                            'top'    => isset( $pad['top'] ) ? (float) $pad['top'] : null,
                            'right'  => isset( $pad['right'] ) ? (float) $pad['right'] : null,
                            'bottom' => isset( $pad['bottom'] ) ? (float) $pad['bottom'] : null,
                            'left'   => isset( $pad['left'] ) ? (float) $pad['left'] : null,
                        );
                    }
                    $applied['padding'] = true;
                }

                if ( array_key_exists( 'margin', $input ) ) {
                    $mar = $input['margin'];
                    if ( is_numeric( $mar ) ) {
                        $settings[ 'margin' . $suffix ] = array(
                            'unit'   => $unit_default,
                            'top'    => (float) $mar,
                            'right'  => (float) $mar,
                            'bottom' => (float) $mar,
                            'left'   => (float) $mar,
                        );
                    } elseif ( is_array( $mar ) ) {
                        $settings[ 'margin' . $suffix ] = array(
                            'unit'   => $mar['unit'] ?? $unit_default,
                            'top'    => isset( $mar['top'] ) ? (float) $mar['top'] : null,
                            'right'  => isset( $mar['right'] ) ? (float) $mar['right'] : null,
                            'bottom' => isset( $mar['bottom'] ) ? (float) $mar['bottom'] : null,
                            'left'   => isset( $mar['left'] ) ? (float) $mar['left'] : null,
                        );
                    }
                    $applied['margin'] = true;
                }

                if ( array_key_exists( 'border_radius', $input ) ) {
                    $br = $input['border_radius'];
                    if ( is_numeric( $br ) ) {
                        $settings[ 'border_radius' . $suffix ] = array(
                            'unit'   => 'px',
                            'top'    => (float) $br,
                            'right'  => (float) $br,
                            'bottom' => (float) $br,
                            'left'   => (float) $br,
                        );
                    } elseif ( is_array( $br ) ) {
                        $settings[ 'border_radius' . $suffix ] = array(
                            'unit'   => $br['unit'] ?? 'px',
                            'top'    => isset( $br['top'] ) ? (float) $br['top'] : null,
                            'right'  => isset( $br['right'] ) ? (float) $br['right'] : null,
                            'bottom' => isset( $br['bottom'] ) ? (float) $br['bottom'] : null,
                            'left'   => isset( $br['left'] ) ? (float) $br['left'] : null,
                        );
                    }
                    $applied['border_radius'] = true;
                }

                if ( array_key_exists( 'border_width', $input ) ) {
                    $bw = $input['border_width'];
                    if ( is_numeric( $bw ) ) {
                        $settings['border_width'] = array(
                            'unit'   => 'px',
                            'top'    => (float) $bw,
                            'right'  => (float) $bw,
                            'bottom' => (float) $bw,
                            'left'   => (float) $bw,
                        );
                    } elseif ( is_array( $bw ) ) {
                        $settings['border_width'] = array(
                            'unit'   => $bw['unit'] ?? 'px',
                            'top'    => isset( $bw['top'] ) ? (float) $bw['top'] : null,
                            'right'  => isset( $bw['right'] ) ? (float) $bw['right'] : null,
                            'bottom' => isset( $bw['bottom'] ) ? (float) $bw['bottom'] : null,
                            'left'   => isset( $bw['left'] ) ? (float) $bw['left'] : null,
                        );
                    }
                    $applied['border_width'] = true;
                }
                if ( isset( $input['border_color'] ) && $input['border_color'] !== '' ) {
                    $settings['border_color'] = $input['border_color'];
                    $applied['border_color']  = $input['border_color'];
                }
                if ( isset( $input['border_style'] ) && $input['border_style'] !== '' ) {
                    $settings['border_border'] = $input['border_style'];
                    $applied['border_style']   = $input['border_style'];
                }

                if ( isset( $input['opacity'] ) && $input['opacity'] !== null ) {
                    $settings[ 'opacity' . $suffix ] = (float) $input['opacity'];
                    $applied['opacity']              = (float) $input['opacity'];
                }
                if ( isset( $input['z_index'] ) && $input['z_index'] !== null ) {
                    $settings[ 'z_index' . $suffix ] = (int) $input['z_index'];
                    $applied['z_index']              = (int) $input['z_index'];
                }

                if ( isset( $input['css_class'] ) && $input['css_class'] !== '' ) {
                    $settings['_css_classes'] = $input['css_class'];
                    $applied['css_class']     = $input['css_class'];
                }

                return null;
            }
        );

        if ( ! $found ) {
            return array(
                'success'    => false,
                'error_code' => 'CONTAINER_NOT_FOUND',
                /* translators: %s: container id */
                'message'    => sprintf( __( "Container with ID '%s' not found", 'hostinger-ai-assistant' ), $container_id ),
            );
        }

        $save_result = $this->save_elementor_data( $post_id, $elementor_data );
        if ( ! $save_result['success'] ) {
            return $save_result;
        }

        return array(
            'success'      => true,
            'post_id'      => $post_id,
            'container_id' => $container_id,
            'device'       => $input['device'] ?? 'desktop',
            'updated'      => $applied,
        );
    }
}
