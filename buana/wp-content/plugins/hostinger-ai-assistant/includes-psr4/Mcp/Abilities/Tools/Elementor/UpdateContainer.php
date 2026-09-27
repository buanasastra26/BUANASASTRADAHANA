<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class UpdateContainer extends ContainerTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-update-container',
            array(
                'label'               => __( 'Update Elementor Container', 'hostinger-ai-assistant' ),
                'description'         => __( 'Updates container layout and structure settings like layout, direction, content width, alignment, spacing, and number of inner columns.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array_merge(
                        $this->schema_post_id(),
                        $this->schema_container_target(),
                        $this->schema_layout_settings(),
                        $this->schema_alignment_and_gaps(),
                        $this->schema_columns_manage()
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
                        'title'           => 'Update Container',
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
        $post_id             = (int) $input['post_id'];
        $container_id        = (string) $input['container_id'];
        $layout              = $input['layout'] ?? null;
        $direction           = $input['direction'] ?? null;
        $content_width       = $input['content_width'] ?? null;
        $align_items         = $input['align_items'] ?? null;
        $justify_content     = $input['justify_content'] ?? null;
        $gap                 = $input['gap'] ?? null;
        $row_gap             = $input['row_gap'] ?? null;
        $column_gap          = $input['column_gap'] ?? null;
        $columns             = isset( $input['columns'] ) ? (int) $input['columns'] : null;
        $preserve_children   = $input['preserve_children'] ?? true;
        $distribute_children = $input['distribute_children'] ?? 'even';
        $css_class           = $input['css_class'] ?? null;

        $result = $this->get_elementor_data( $post_id );
        if ( isset( $result['error_code'] ) ) {
            return $result;
        }
        $elementor_data = $result['data'];

        $found = $this->update_widget_in_tree(
            $elementor_data,
            $container_id,
            function ( &$container ) use (
                $layout,
                $direction,
                $content_width,
                $align_items,
                $justify_content,
                $gap,
                $row_gap,
                $column_gap,
                $columns,
                $preserve_children,
                $distribute_children,
                $css_class
            ) {
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

                if ( $layout ) {
                    if ( $layout === 'flex' ) {
                        unset( $settings['layout'] );
                        if ( $direction ) {
                            $settings['flex_direction'] = $direction;
                        } elseif ( ! isset( $settings['flex_direction'] ) ) {
                            $settings['flex_direction'] = 'row';
                        }
                    } else {
                        $settings['layout'] = 'grid';
                        unset( $settings['flex_direction'] );
                    }
                }
                if ( $direction && ( $layout === 'flex' || ! isset( $settings['layout'] ) ) ) {
                    $settings['flex_direction'] = $direction;
                }
                if ( $content_width ) {
                    $settings['content_width'] = $content_width;
                }

                if ( $align_items ) {
                    $settings['align_items'] = $align_items;
                }
                if ( $justify_content ) {
                    $settings['justify_content'] = $justify_content;
                }
                if ( $gap !== null ) {
                    $settings['gap'] = $gap;
                }
                if ( $row_gap !== null ) {
                    $settings['row_gap'] = $row_gap;
                }
                if ( $column_gap !== null ) {
                    $settings['column_gap'] = $column_gap;
                }
                if ( $css_class !== null ) {
                    $settings['_css_classes'] = $css_class;
                }

                if ( $columns !== null && $columns >= 1 ) {
                    if ( ! isset( $container['elements'] ) || ! is_array( $container['elements'] ) ) {
                        $container['elements'] = array();
                    }
                    $current = count( $container['elements'] );

                    if ( $current < $columns ) {
                        $to_add = $columns - $current;
                        for ( $i = 0; $i < $to_add; $i++ ) {
                            $container['elements'][] = array(
                                'id'       => $this->gen_id(),
                                'elType'   => 'container',
                                'settings' => array(),
                                'elements' => array(),
                                'isInner'  => true,
                            );
                        }
                    } elseif ( $current > $columns ) {
                        $removed = array_splice( $container['elements'], $columns );

                        if ( $preserve_children ) {
                            $all_children = array();
                            foreach ( $removed as $col ) {
                                if ( isset( $col['elements'] ) && is_array( $col['elements'] ) ) {
                                    $all_children = array_merge( $all_children, $col['elements'] );
                                }
                            }

                            if ( ! empty( $all_children ) && ! empty( $container['elements'] ) ) {
                                if ( $distribute_children === 'first' ) {
                                    $target_idx = 0;
                                    if ( ! isset( $container['elements'][ $target_idx ]['elements'] ) || ! is_array( $container['elements'][ $target_idx ]['elements'] ) ) {
                                        $container['elements'][ $target_idx ]['elements'] = array();
                                    }
                                    array_splice( $container['elements'][ $target_idx ]['elements'], count( $container['elements'][ $target_idx ]['elements'] ), 0, $all_children );
                                } elseif ( $distribute_children === 'last' ) {
                                    $last = count( $container['elements'] ) - 1;
                                    if ( ! isset( $container['elements'][ $last ]['elements'] ) || ! is_array( $container['elements'][ $last ]['elements'] ) ) {
                                        $container['elements'][ $last ]['elements'] = array();
                                    }
                                    array_splice( $container['elements'][ $last ]['elements'], count( $container['elements'][ $last ]['elements'] ), 0, $all_children );
                                } else {
                                    $k = 0;
                                    $n = count( $container['elements'] );
                                    foreach ( $all_children as $child ) {
                                        $target = $k % $n;
                                        if ( ! isset( $container['elements'][ $target ]['elements'] ) || ! is_array( $container['elements'][ $target ]['elements'] ) ) {
                                            $container['elements'][ $target ]['elements'] = array();
                                        }
                                        $container['elements'][ $target ]['elements'][] = $child;
                                        $k++;
                                    }
                                }
                            }
                        }
                    }
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
            'updated'      => array_filter(
                array(
                    'layout'          => $layout,
                    'direction'       => $direction,
                    'content_width'   => $content_width,
                    'align_items'     => $align_items,
                    'justify_content' => $justify_content,
                    'gap'             => $gap,
                    'row_gap'         => $row_gap,
                    'column_gap'      => $column_gap,
                    'columns'         => $columns,
                    'css_class'       => $css_class,
                ),
                static function ( $v ) {
                    return $v !== null && $v !== ''; }
            ),
        );
    }
}
