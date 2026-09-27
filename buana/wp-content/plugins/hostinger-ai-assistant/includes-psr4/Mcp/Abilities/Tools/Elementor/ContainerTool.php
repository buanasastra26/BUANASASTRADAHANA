<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Common helpers and schema fragments for Elementor Container tools
 * (create/update/delete).
 */
abstract class ContainerTool extends BaseElementorTool {
    /**
     * Background content keys that are cleared before applying a new background,
     * so switching background type never leaves stale data behind.
     */
    private const BACKGROUND_CONTENT_KEYS = array(
        'background_color',
        'background_color_b',
        'background_color_stop',
        'background_color_b_stop',
        'background_gradient_type',
        'background_gradient_angle',
        'background_gradient_position',
        'background_image',
        'background_video_link',
        'background_video_fallback',
        'background_video_start',
        'background_video_end',
    );

    protected function schema_background(): array {
        return array(
            'background' => array(
                'type'        => 'object',
                'description' => __( 'Full background definition (color, image, gradient or video). Replaces any existing background on the container. Use type "none" to remove the background. Takes precedence over background_color.', 'hostinger-ai-assistant' ),
                'properties'  => array(
                    'type'               => array(
                        'type'        => 'string',
                        'enum'        => array( 'color', 'image', 'gradient', 'video', 'none' ),
                        'description' => __( 'Background type to apply', 'hostinger-ai-assistant' ),
                    ),
                    'color'              => array(
                        'type'        => 'string',
                        'description' => __( 'Background color (for type "color"), or the start color (for type "gradient")', 'hostinger-ai-assistant' ),
                    ),
                    'color_b'            => array(
                        'type'        => 'string',
                        'description' => __( 'Gradient end color (for type "gradient")', 'hostinger-ai-assistant' ),
                    ),
                    'gradient_type'      => array(
                        'type'        => 'string',
                        'enum'        => array( 'linear', 'radial' ),
                        'default'     => 'linear',
                        'description' => __( 'Gradient style (for type "gradient")', 'hostinger-ai-assistant' ),
                    ),
                    'gradient_angle'     => array(
                        'type'        => 'number',
                        'description' => __( 'Linear gradient angle in degrees (for type "gradient")', 'hostinger-ai-assistant' ),
                    ),
                    'image_url'          => array(
                        'type'        => 'string',
                        'description' => __( 'Image URL (for type "image")', 'hostinger-ai-assistant' ),
                    ),
                    'image_id'           => array(
                        'type'        => 'integer',
                        'description' => __( 'Optional media library attachment ID for the image', 'hostinger-ai-assistant' ),
                    ),
                    'video_link'         => array(
                        'type'        => 'string',
                        'description' => __( 'Video URL — self-hosted mp4 or YouTube/Vimeo (for type "video")', 'hostinger-ai-assistant' ),
                    ),
                    'video_fallback_url' => array(
                        'type'        => 'string',
                        'description' => __( 'Optional fallback image URL shown while/if the video cannot play (for type "video")', 'hostinger-ai-assistant' ),
                    ),
                ),
                'required'    => array( 'type' ),
            ),
        );
    }

    protected function apply_background( array &$settings, array $bg ): array {
        foreach ( self::BACKGROUND_CONTENT_KEYS as $key ) {
            unset( $settings[ $key ] );
        }

        $type = $bg['type'] ?? '';

        if ( $type === '' || $type === 'none' ) {
            $settings['background_background'] = '';

            return array( 'type' => 'none' );
        }

        $applied = array( 'type' => $type );

        switch ( $type ) {
            case 'color':
                $settings['background_background'] = 'classic';
                if ( ! empty( $bg['color'] ) ) {
                    $settings['background_color'] = $bg['color'];
                    $applied['color']             = $bg['color'];
                }
                break;

            case 'image':
                $settings['background_background'] = 'classic';
                if ( ! empty( $bg['image_url'] ) ) {
                    $settings['background_image'] = array(
                        'url' => $bg['image_url'],
                        'id'  => $bg['image_id'] ?? '',
                    );
                    $applied['image_url']         = $bg['image_url'];
                }
                break;

            case 'gradient':
                $settings['background_background']    = 'gradient';
                $gradient_type                        = $bg['gradient_type'] ?? 'linear';
                $settings['background_gradient_type'] = $gradient_type;
                $applied['gradient_type']             = $gradient_type;

                if ( ! empty( $bg['color'] ) ) {
                    $settings['background_color'] = $bg['color'];
                    $applied['color']             = $bg['color'];
                }
                if ( ! empty( $bg['color_b'] ) ) {
                    $settings['background_color_b'] = $bg['color_b'];
                    $applied['color_b']             = $bg['color_b'];
                }
                if ( $gradient_type === 'linear' && isset( $bg['gradient_angle'] ) ) {
                    $settings['background_gradient_angle'] = array(
                        'unit' => 'deg',
                        'size' => (float) $bg['gradient_angle'],
                    );
                    $applied['gradient_angle']             = (float) $bg['gradient_angle'];
                }
                break;

            case 'video':
                $settings['background_background'] = 'video';
                if ( ! empty( $bg['video_link'] ) ) {
                    $settings['background_video_link'] = $bg['video_link'];
                    $applied['video_link']             = $bg['video_link'];
                }
                if ( ! empty( $bg['video_fallback_url'] ) ) {
                    $settings['background_video_fallback'] = array(
                        'url' => $bg['video_fallback_url'],
                        'id'  => '',
                    );
                    $applied['video_fallback_url']         = $bg['video_fallback_url'];
                }
                break;
        }

        return $applied;
    }

    protected function schema_post_id(): array {
        return array(
            'post_id' => array(
                'type'        => 'integer',
                'description' => __( 'The ID of the post/page to modify', 'hostinger-ai-assistant' ),
            ),
        );
    }

    protected function schema_container_target(): array {
        return array(
            'container_id' => array(
                'type'        => 'string',
                'description' => __( 'The ID of the target container', 'hostinger-ai-assistant' ),
            ),
        );
    }

    protected function schema_parent_and_position(): array {
        return array(
            'parent_id'  => array(
                'type'        => 'string',
                'description' => __( 'Optional parent container ID. If omitted, operations happen at the root level.', 'hostinger-ai-assistant' ),
            ),
            'position'   => array(
                'type'        => 'string',
                'enum'        => array( 'start', 'end', 'before', 'after', 'index' ),
                'default'     => 'end',
                'description' => __( 'Insertion position relative to list (start/end), a sibling (before/after) or a specific index.', 'hostinger-ai-assistant' ),
            ),
            'sibling_id' => array(
                'type'        => 'string',
                'description' => __( 'Required when position is before/after. The sibling element ID.', 'hostinger-ai-assistant' ),
            ),
            'index'      => array(
                'type'        => 'integer',
                'minimum'     => 0,
                'description' => __( 'Zero-based index to insert at when position is index.', 'hostinger-ai-assistant' ),
            ),
        );
    }

    protected function schema_layout_settings(): array {
        return array(
            'layout'        => array(
                'type'        => 'string',
                'enum'        => array( 'flex', 'grid' ),
                'description' => __( 'Container layout type', 'hostinger-ai-assistant' ),
            ),
            'direction'     => array(
                'type'        => 'string',
                'enum'        => array( 'row', 'column' ),
                'description' => __( 'Flex direction (row or column). Applies to flex layout.', 'hostinger-ai-assistant' ),
            ),
            'content_width' => array(
                'type'        => 'string',
                'enum'        => array( 'boxed', 'full_width' ),
                'description' => __( 'Content width behavior for the container', 'hostinger-ai-assistant' ),
            ),
            'css_class'     => array(
                'type'        => 'string',
                'description' => __( 'Optional CSS class to assign/update on the container', 'hostinger-ai-assistant' ),
            ),
        );
    }

    protected function schema_alignment_and_gaps(): array {
        return array(
            'align_items'     => array(
                'type'        => 'string',
                'enum'        => array( 'flex-start', 'center', 'flex-end', 'stretch', 'baseline' ),
                'description' => __( 'Cross‑axis alignment for flex containers', 'hostinger-ai-assistant' ),
            ),
            'justify_content' => array(
                'type'        => 'string',
                'enum'        => array( 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly' ),
                'description' => __( 'Main‑axis alignment for flex containers', 'hostinger-ai-assistant' ),
            ),
            'gap'             => array(
                'type'        => 'number',
                'minimum'     => 0,
                'description' => __( 'Overall gap between child items (px)', 'hostinger-ai-assistant' ),
            ),
            'row_gap'         => array(
                'type'        => 'number',
                'minimum'     => 0,
                'description' => __( 'Row gap for grid or multi‑row flex (px)', 'hostinger-ai-assistant' ),
            ),
            'column_gap'      => array(
                'type'        => 'number',
                'minimum'     => 0,
                'description' => __( 'Column gap for grid or flex (px)', 'hostinger-ai-assistant' ),
            ),
        );
    }

    protected function schema_columns_simple(): array {
        return array(
            'columns' => array(
                'type'        => 'integer',
                'minimum'     => 1,
                'maximum'     => 12,
                'description' => __( 'Number of inner columns (containers)', 'hostinger-ai-assistant' ),
            ),
        );
    }

    protected function schema_columns_manage(): array {
        return array(
            'columns'             => array(
                'type'        => 'integer',
                'minimum'     => 1,
                'maximum'     => 12,
                'description' => __( 'Target number of inner columns (containers)', 'hostinger-ai-assistant' ),
            ),
            'preserve_children'   => array(
                'type'        => 'boolean',
                'default'     => true,
                'description' => __( 'When reducing columns, keep removed columns’ children by moving them into a remaining column', 'hostinger-ai-assistant' ),
            ),
            'distribute_children' => array(
                'type'        => 'string',
                'enum'        => array( 'first', 'last', 'even' ),
                'default'     => 'even',
                'description' => __( 'If preserving children, where to place them into remaining columns', 'hostinger-ai-assistant' ),
            ),
        );
    }

    protected function gen_id(): string {
        $id = substr( str_replace( array( '/', '+', '=' ), '', base64_encode( random_bytes( 6 ) ) ), 0, 8 );
        return strtolower( $id );
    }

    protected function make_container( string $layout, string $direction, string $content_width, string $css_class, int $columns, bool $is_inner ): array {
        $id       = $this->gen_id();
        $settings = array( 'content_width' => $content_width );

        if ( $layout === 'flex' ) {
            $settings['flex_direction'] = ( $direction !== '' ) ? $direction : 'row';
        } else {
            $settings['layout'] = 'grid';
        }

        if ( ! empty( $css_class ) ) {
            $settings['_css_classes'] = $css_class;
        }

        $container = array(
            'id'       => $id,
            'elType'   => 'container',
            'settings' => $settings,
            'elements' => array(),
            'isInner'  => $is_inner,
        );

        if ( $columns > 1 ) {
            for ( $i = 0; $i < $columns; $i++ ) {
                $container['elements'][] = array(
                    'id'       => $this->gen_id(),
                    'elType'   => 'container',
                    'settings' => array(),
                    'elements' => array(),
                    'isInner'  => true,
                );
            }
        }

        return $container;
    }

    protected function insert_into_list( array &$items, array $element, string $position, ?string $sibling_id, ?int $index, ?int &$last_index = null ): bool {
        $count = count( $items );
        switch ( $position ) {
            case 'start':
                array_unshift( $items, $element );
                $last_index = 0;
                return true;
            case 'end':
                $items[]    = $element;
                $last_index = $count;
                return true;
            case 'index':
                if ( $index === null ) {
                    return false;
                }
                $idx = max( 0, min( $index, $count ) );
                array_splice( $items, $idx, 0, array( $element ) );
                $last_index = $idx;
                return true;
            case 'before':
            case 'after':
                if ( empty( $sibling_id ) ) {
                    return false;
                }
                foreach ( $items as $i => $el ) {
                    if ( isset( $el['id'] ) && $el['id'] === $sibling_id ) {
                        $idx = $position === 'before' ? $i : $i + 1;
                        array_splice( $items, $idx, 0, array( $element ) );
                        $last_index = $idx;
                        return true;
                    }
                }
                return false;
            default:
                return false;
        }
    }

    protected function delete_element_in_tree( array &$elements, string $target_id, bool $preserve_children, bool $confirm, array &$summary, bool &$needs_confirmation ): bool {
        foreach ( $elements as $index => &$element ) {
            if ( isset( $element['id'] ) && $element['id'] === $target_id ) {
                $subtree = $element;
                $summary = $this->compute_subtree_summary( $subtree );

                if ( ! $preserve_children && $this->is_complex_subtree( $summary ) && ! $confirm ) {
                    $needs_confirmation = true;
                    return false;
                }

                $children = isset( $element['elements'] ) && is_array( $element['elements'] ) ? $element['elements'] : array();

                if ( $preserve_children && ! empty( $children ) ) {
                    array_splice( $elements, $index, 1, $children );
                } else {
                    array_splice( $elements, $index, 1 );
                }

                return true;
            }

            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                if ( $this->delete_element_in_tree( $element['elements'], $target_id, $preserve_children, $confirm, $summary, $needs_confirmation ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function compute_subtree_summary( array $element ): array {
        $summary = array(
            'total_nodes' => 0,
            'containers'  => 0,
            'widgets'     => 0,
            'max_depth'   => 0,
        );

        $this->walk_subtree( $element, 0, $summary );

        return $summary;
    }

    private function walk_subtree( array $node, int $depth, array &$summary ): void {
        ++$summary['total_nodes'];
        $summary['max_depth'] = max( $summary['max_depth'], $depth );

        $type = $node['elType'] ?? '';
        if ( $type === 'container' ) {
            ++$summary['containers'];
        } elseif ( isset( $node['widgetType'] ) ) {
            ++$summary['widgets'];
        }

        if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
            foreach ( $node['elements'] as $child ) {
                $this->walk_subtree( $child, $depth + 1, $summary );
            }
        }
    }

    protected function is_complex_subtree( array $summary ): bool {
        if ( ( $summary['total_nodes'] ?? 0 ) >= 6 ) {
            return true;
        }
        if ( ( $summary['widgets'] ?? 0 ) >= 3 ) {
            return true;
        }
        if ( ( $summary['max_depth'] ?? 0 ) >= 3 ) {
            return true;
        }
        return false;
    }
}
