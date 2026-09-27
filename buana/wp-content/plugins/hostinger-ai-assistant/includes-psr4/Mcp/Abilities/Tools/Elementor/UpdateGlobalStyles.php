<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class UpdateGlobalStyles extends BaseElementorTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-update-global-styles',
            array(
                'label'               => __( 'Update Elementor Global Styles', 'hostinger-ai-assistant' ),
                'description'         => __( 'Updates Elementor global colors and/or typography presets in the active kit in a single call. Intended for intents like "Change primary color to red" and "Change heading font to Arial" with one request. Triggers site-wide CSS regeneration.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'colors'     => array(
                            'type'        => 'array',
                            'description' => __( 'List of global color updates', 'hostinger-ai-assistant' ),
                            'items'       => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'system_key'      => array(
                                        'type'        => 'string',
                                        'description' => __( 'System color key to update (optional)', 'hostinger-ai-assistant' ),
                                        'enum'        => array( 'primary', 'secondary', 'text', 'accent' ),
                                    ),
                                    'global_color_id' => array(
                                        'type'        => 'string',
                                        'description' => __( 'Custom global color _id to update (optional)', 'hostinger-ai-assistant' ),
                                    ),
                                    'title'           => array(
                                        'type'        => 'string',
                                        'description' => __( 'Optional new title/label for the color', 'hostinger-ai-assistant' ),
                                    ),
                                    'color'           => array(
                                        'type'        => 'string',
                                        'description' => __( 'New color value (e.g. #FF0000 or rgba(255,0,0,1))', 'hostinger-ai-assistant' ),
                                    ),
                                ),
                                'required'   => array( 'color' ),
                            ),
                        ),
                        'typography' => array(
                            'type'        => 'array',
                            'description' => __( 'List of typography preset updates', 'hostinger-ai-assistant' ),
                            'items'       => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'system_key'      => array(
                                        'type'        => 'string',
                                        'description' => __( 'System typography preset key to update (optional)', 'hostinger-ai-assistant' ),
                                        'enum'        => array( 'primary', 'secondary', 'text', 'accent' ),
                                    ),
                                    'preset_id'       => array(
                                        'type'        => 'string',
                                        'description' => __( 'Custom typography preset _id to update (optional)', 'hostinger-ai-assistant' ),
                                    ),
                                    'title'           => array(
                                        'type'        => 'string',
                                        'description' => __( 'Optional new title/label', 'hostinger-ai-assistant' ),
                                    ),
                                    'font_family'     => array( 'type' => 'string' ),
                                    'font_weight'     => array( 'type' => 'string' ),
                                    'font_size'       => array( 'type' => array( 'number', 'string', 'object' ) ),
                                    'line_height'     => array( 'type' => array( 'number', 'string', 'object' ) ),
                                    'letter_spacing'  => array( 'type' => array( 'number', 'string', 'object' ) ),
                                    'text_transform'  => array( 'type' => 'string' ),
                                    'font_style'      => array( 'type' => 'string' ),
                                    'text_decoration' => array( 'type' => 'string' ),
                                ),
                            ),
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
                        'title'           => 'Update Global Styles',
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
        $colors     = $input['colors'] ?? array();
        $typography = $input['typography'] ?? array();

        if ( empty( $colors ) && empty( $typography ) ) {
            return array(
                'success'    => false,
                'error_code' => 'NO_UPDATES',
                'message'    => __( 'Provide colors and/or typography updates to apply.', 'hostinger-ai-assistant' ),
            );
        }

        $active_kit_id = (int) get_option( 'elementor_active_kit' );
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

        $results = array(
            'colors'     => array(),
            'typography' => array(),
        );

        if ( is_array( $colors ) && ! empty( $colors ) ) {
            $system_colors = $kit_settings['system_colors'] ?? array();
            $custom_colors = $kit_settings['custom_colors'] ?? array();

            foreach ( $colors as $update ) {
                if ( ! is_array( $update ) || ! isset( $update['color'] ) ) {
                    continue;
                }

                $applied   = false;
                $new_title = $update['title'] ?? null;

                if ( ! empty( $update['system_key'] ) ) {
                    foreach ( $system_colors as &$c ) {
                        if ( isset( $c['_id'] ) && $c['_id'] === $update['system_key'] ) {
                            $prev       = $c;
                            $c['color'] = $update['color'];
                            if ( $new_title !== null ) {
                                $c['title'] = $new_title;
                            }
                            $results['colors'][] = array(
                                'target'   => 'system',
                                'id'       => $update['system_key'],
                                'previous' => $this->sanitize_color_payload( $prev ),
                                'current'  => $this->sanitize_color_payload( $c ),
                            );
                            $applied             = true;
                            break;
                        }
                    }
                    unset( $c );
                }

                if ( ! $applied && ! empty( $update['global_color_id'] ) ) {
                    foreach ( $custom_colors as &$c ) {
                        if ( isset( $c['_id'] ) && $c['_id'] === $update['global_color_id'] ) {
                            $prev       = $c;
                            $c['color'] = $update['color'];
                            if ( $new_title !== null ) {
                                $c['title'] = $new_title;
                            }
                            $results['colors'][] = array(
                                'target'   => 'custom',
                                'id'       => $update['global_color_id'],
                                'previous' => $this->sanitize_color_payload( $prev ),
                                'current'  => $this->sanitize_color_payload( $c ),
                            );
                            $applied             = true;
                            break;
                        }
                    }
                    unset( $c );
                }

                if ( ! $applied ) {
                    $results['colors'][] = array(
                        'error'      => true,
                        'error_code' => 'COLOR_NOT_FOUND',
                        'message'    => __( 'Target global color not found in the active kit', 'hostinger-ai-assistant' ),
                        'input'      => $this->sanitize_color_payload_from_input( $update ),
                    );
                }
            }

            $kit_settings['system_colors'] = $system_colors;
            $kit_settings['custom_colors'] = $custom_colors;
        }

        if ( is_array( $typography ) && ! empty( $typography ) ) {
            $system_typo = $kit_settings['system_typography'] ?? array();
            $custom_typo = $kit_settings['custom_typography'] ?? array();

            foreach ( $typography as $update ) {
                if ( ! is_array( $update ) ) {
                    continue;
                }

                $applied = false;

                if ( ! empty( $update['system_key'] ) ) {
                    foreach ( $system_typo as &$p ) {
                        if ( isset( $p['_id'] ) && $p['_id'] === $update['system_key'] ) {
                            $prev = $p;
                            $this->apply_typography_changes( $p, $update );
                            $results['typography'][] = array(
                                'target'   => 'system',
                                'id'       => $update['system_key'],
                                'previous' => $this->compact_typography_payload( $prev ),
                                'current'  => $this->compact_typography_payload( $p ),
                            );
                            $applied                 = true;
                            break;
                        }
                    }
                    unset( $p );
                }

                if ( ! $applied && ! empty( $update['preset_id'] ) ) {
                    foreach ( $custom_typo as &$p ) {
                        if ( isset( $p['_id'] ) && $p['_id'] === $update['preset_id'] ) {
                            $prev = $p;
                            $this->apply_typography_changes( $p, $update );
                            $results['typography'][] = array(
                                'target'   => 'custom',
                                'id'       => $update['preset_id'],
                                'previous' => $this->compact_typography_payload( $prev ),
                                'current'  => $this->compact_typography_payload( $p ),
                            );
                            $applied                 = true;
                            break;
                        }
                    }
                    unset( $p );
                }

                if ( ! $applied ) {
                    $results['typography'][] = array(
                        'error'      => true,
                        'error_code' => 'TYPO_PRESET_NOT_FOUND',
                        'message'    => __( 'Target typography preset not found in the active kit', 'hostinger-ai-assistant' ),
                        'input'      => $this->compact_typography_input( $update ),
                    );
                }
            }

            $kit_settings['system_typography'] = $system_typo;
            $kit_settings['custom_typography'] = $custom_typo;
        }

        $has_success = $this->has_any_success( $results );
        if ( $has_success ) {
            update_post_meta( $active_kit_id, '_elementor_page_settings', $kit_settings );
            $this->regenerate_global_css();
        }

        return array(
            'success' => $has_success,
            'kit_id'  => $active_kit_id,
            'results' => $results,
        );
    }

    private function has_any_success( array $results ): bool {
        foreach ( array( 'colors', 'typography' ) as $group ) {
            if ( empty( $results[ $group ] ) ) {
                continue;
            }
            foreach ( $results[ $group ] as $item ) {
                if ( empty( $item['error'] ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    private function sanitize_color_payload( array $c ): array {
        return array(
            'id'    => $c['_id'] ?? '',
            'title' => $c['title'] ?? '',
            'color' => $c['color'] ?? '',
        );
    }

    private function sanitize_color_payload_from_input( array $in ): array {
        return array(
            'id'    => $in['global_color_id'] ?? ( $in['system_key'] ?? '' ),
            'title' => $in['title'] ?? '',
            'color' => $in['color'] ?? '',
        );
    }

    private function apply_typography_changes( array &$preset, array $input ): void {
        if ( isset( $input['title'] ) && $input['title'] !== null ) {
            $preset['title'] = $input['title'];
        }

        if ( isset( $input['font_family'] ) && $input['font_family'] !== null ) {
            $preset['typography_font_family'] = $input['font_family'];
        }

        if ( isset( $input['font_weight'] ) && $input['font_weight'] !== null ) {
            $preset['typography_font_weight'] = (string) $input['font_weight'];
        }

        if ( array_key_exists( 'font_size', $input ) ) {
            $preset['typography_font_size'] = $this->normalize_dimension_value( $input['font_size'], 'px' );
        }

        if ( array_key_exists( 'line_height', $input ) ) {
            $preset['typography_line_height'] = $this->normalize_dimension_value( $input['line_height'], '' );
        }

        if ( array_key_exists( 'letter_spacing', $input ) ) {
            $preset['typography_letter_spacing'] = $this->normalize_dimension_value( $input['letter_spacing'], 'px' );
        }

        if ( isset( $input['text_transform'] ) && $input['text_transform'] !== null ) {
            $preset['typography_text_transform'] = $input['text_transform'];
        }

        if ( isset( $input['font_style'] ) && $input['font_style'] !== null ) {
            $preset['typography_font_style'] = $input['font_style'];
        }

        if ( isset( $input['text_decoration'] ) && $input['text_decoration'] !== null ) {
            $preset['typography_text_decoration'] = $input['text_decoration'];
        }
    }

    private function normalize_dimension_value( $value, string $default_unit ): array {
        if ( is_array( $value ) ) {
            $unit = isset( $value['unit'] ) ? (string) $value['unit'] : $default_unit;
            $size = isset( $value['size'] ) ? $value['size'] : null;
            return array(
                'unit' => $unit,
                'size' => $size,
            );
        }

        if ( is_numeric( $value ) ) {
            return array(
                'unit' => $default_unit,
                'size' => $value + 0,
            );
        }

        if ( is_string( $value ) ) {
            if ( preg_match( '/^([0-9]+(?:\.[0-9]+)?)([a-z%]*)$/i', $value, $m ) ) {
                $size = (float) $m[1];
                $unit = $m[2] !== '' ? $m[2] : $default_unit;
                return array(
                    'unit' => $unit,
                    'size' => $size,
                );
            }
        }

        return array(
            'unit' => $default_unit,
            'size' => $value,
        );
    }

    private function compact_typography_payload( array $p ): array {
        $out = array(
            'id'    => $p['_id'] ?? '',
            'title' => $p['title'] ?? '',
        );

        $keys = array(
            'typography_font_family'     => 'font_family',
            'typography_font_weight'     => 'font_weight',
            'typography_font_size'       => 'font_size',
            'typography_line_height'     => 'line_height',
            'typography_letter_spacing'  => 'letter_spacing',
            'typography_text_transform'  => 'text_transform',
            'typography_font_style'      => 'font_style',
            'typography_text_decoration' => 'text_decoration',
        );

        foreach ( $keys as $k => $alias ) {
            if ( isset( $p[ $k ] ) ) {
                $out[ $alias ] = $p[ $k ];
            }
        }

        return $out;
    }

    private function compact_typography_input( array $in ): array {
        $out = array(
            'id'    => $in['preset_id'] ?? ( $in['system_key'] ?? '' ),
            'title' => $in['title'] ?? '',
        );
        foreach ( array( 'font_family', 'font_weight', 'font_size', 'line_height', 'letter_spacing', 'text_transform', 'font_style', 'text_decoration' ) as $k ) {
            if ( isset( $in[ $k ] ) ) {
                $out[ $k ] = $in[ $k ];
            }
        }
        return $out;
    }

    private function regenerate_global_css(): void {
        if ( class_exists( '\\Elementor\\Plugin' ) ) {
            $plugin = \Elementor\Plugin::instance();
            if ( isset( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) {
                $plugin->files_manager->clear_cache();
            }
        }
    }
}
