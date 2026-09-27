<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

use Hostinger\AiAssistant\Mcp\Dto\Elementor\WidgetSearchQuery;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

abstract class BaseElementorTool {
    protected string $category = 'hostinger-ai-assistant';
    protected string $type     = 'tool';

    abstract public function register(): void;

    protected function is_elementor_post( int $post_id ): bool {
        $edit_mode = get_post_meta( $post_id, '_elementor_edit_mode', true );

        return $edit_mode === 'builder';
    }

    protected function get_elementor_data( int $post_id ): array {
        if ( ! $this->is_elementor_post( $post_id ) ) {
            return array(
                'success'    => false,
                'error_code' => 'NOT_ELEMENTOR_PAGE',
                'message'    => __( 'This post does not use Elementor builder', 'hostinger-ai-assistant' ),
            );
        }

        $data = get_post_meta( $post_id, '_elementor_data', true );

        if ( empty( $data ) ) {
            return array(
                'success'    => false,
                'error_code' => 'NO_ELEMENTOR_DATA',
                'message'    => __( 'No Elementor data found for this post', 'hostinger-ai-assistant' ),
            );
        }

        $elementor_data = json_decode( $data, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return array(
                'success'    => false,
                'error_code' => 'INVALID_JSON',
                'message'    => __( 'Could not parse Elementor data: ', 'hostinger-ai-assistant' ) . json_last_error_msg(),
            );
        }

        return array( 'data' => $elementor_data );
    }

    protected function save_elementor_data( int $post_id, array $elementor_data ): array {
        $json_data = wp_json_encode( $elementor_data );

        if ( $json_data === false ) {
            return array(
                'success'    => false,
                'error_code' => 'JSON_ENCODE_FAILED',
                'message'    => 'Failed to encode Elementor data',
            );
        }

        $old_data = get_post_meta( $post_id, '_elementor_data', true );
        update_post_meta( $post_id, '_elementor_data_backup', $old_data );

        $updated = update_post_meta( $post_id, '_elementor_data', wp_slash( $json_data ) );

        if ( $updated === false ) {
            return array(
                'success'    => false,
                'error_code' => 'SAVE_FAILED',
                'message'    => 'Failed to save Elementor data to database',
            );
        }

        delete_post_meta( $post_id, '_elementor_css' );
        delete_post_meta( $post_id, '_elementor_element_cache' );

        wp_update_post(
            array(
                'ID'                => $post_id,
                'post_modified'     => current_time( 'mysql' ),
                'post_modified_gmt' => current_time( 'mysql', 1 ),
            )
        );

        return array( 'success' => true );
    }

    protected function update_widget_in_tree( array &$elements, string $widget_id, callable $callback ): bool {
        foreach ( $elements as &$element ) {
            if ( isset( $element['id'] ) && $element['id'] === $widget_id ) {
                $result = $callback( $element );

                if ( is_array( $result ) && isset( $result['error_code'] ) ) {
                    return false;
                }

                return true;
            }

            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                if ( $this->update_widget_in_tree( $element['elements'], $widget_id, $callback ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function find_element_by_id( array $elements, string $widget_id, array $path ): ?array {
        foreach ( $elements as $element ) {
            if ( isset( $element['id'] ) && $element['id'] === $widget_id ) {
                $result = array(
                    'id'    => $element['id'],
                    'type'  => $element['elType'] ?? '',
                    'depth' => count( $path ),
                    'path'  => $path,
                );

                if ( isset( $element['widgetType'] ) ) {
                    $result['widget_type'] = $element['widgetType'];
                }

                if ( ! empty( $path ) ) {
                    $result['parent_id'] = end( $path );
                }

                if ( isset( $element['settings'] ) ) {
                    $result['settings'] = $element['settings'];
                }

                return $result;
            }

            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $new_path = array_merge( $path, array( $element['id'] ) );
                $result   = $this->find_element_by_id( $element['elements'], $widget_id, $new_path );
                if ( $result ) {
                    return $result;
                }
            }
        }

        return null;
    }

    protected function build_structure_tree( array $elements, int $depth, int $max_depth, bool $include_settings, array $path = array() ): array {
        $structure = array();

        if ( $depth >= $max_depth ) {
            return $structure;
        }

        foreach ( $elements as $element ) {
            $item = array(
                'id'    => $element['id'] ?? '',
                'type'  => $element['elType'] ?? '',
                'depth' => $depth,
            );

            if ( isset( $element['widgetType'] ) ) {
                $item['widget_type'] = $element['widgetType'];
            }

            if ( ! empty( $path ) ) {
                $item['parent_id'] = end( $path );
            }

            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $item['children_count'] = count( $element['elements'] );
            } else {
                $item['children_count'] = 0;
            }

            if ( ( $element['elType'] ?? '' ) === 'container' ) {
                $background = $this->describe_background( $element['settings'] ?? array() );
                if ( $background !== null ) {
                    $item['background'] = $background;
                }
            }

            if ( $include_settings && isset( $element['settings'] ) ) {
                $item['settings_summary'] = $this->get_settings_summary( $element );
            }

            if ( isset( $element['widgetType'] ) ) {
                $item['content_preview'] = $this->get_content_preview( $element );
            }

            $structure[] = $item;

            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $new_path    = array_merge( $path, array( $element['id'] ) );
                $child_items = $this->build_structure_tree(
                    $element['elements'],
                    $depth + 1,
                    $max_depth,
                    $include_settings,
                    $new_path
                );
                $structure   = array_merge( $structure, $child_items );
            }
        }

        return $structure;
    }

    protected function search_widgets( array $elements, WidgetSearchQuery $query, array &$found, array $path = array(), int $depth = 0 ): void {
        if ( $depth >= $query->max_depth ) {
            return;
        }

        foreach ( $elements as $element ) {
            $current_path = array_merge( $path, array( $element['id'] ) );

            if ( isset( $element['widgetType'] ) && in_array( $element['widgetType'], $query->widget_types, true ) ) {
                if ( ! empty( $query->css_class_filter ) ) {
                    $css_classes = $element['settings']['_css_classes'] ?? '';
                    if ( str_contains( $query->css_class_filter, '*' ) ) {
                        $pattern = '/' . str_replace( '*', '.*', preg_quote( $query->css_class_filter, '/' ) ) . '/';
                        if ( ! preg_match( $pattern, $css_classes ) ) {
                            continue;
                        }
                    } elseif ( ! str_contains( $css_classes, $query->css_class_filter ) ) {
                        continue;
                    }
                }

                $widget = array(
                    'id'          => $element['id'],
                    'widget_type' => $element['widgetType'],
                    'depth'       => $depth,
                    'path'        => implode( ' > ', $current_path ),
                );

                if ( ! empty( $path ) ) {
                    $widget['parent_id'] = end( $path );
                }

                if ( $query->include_settings && isset( $element['settings'] ) ) {
                    $widget['settings'] = $element['settings'];
                }

                $found[] = $widget;
            }

            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $this->search_widgets(
                    $element['elements'],
                    $query,
                    $found,
                    $current_path,
                    $depth + 1
                );
            }
        }
    }

    protected function get_settings_summary( array $element ): string {
        $settings = $element['settings'] ?? array();
        $summary  = array();

        if ( isset( $settings['flex_direction'] ) ) {
            $summary[] = 'Direction: ' . $settings['flex_direction'];
        }
        if ( isset( $settings['content_width'] ) ) {
            $summary[] = 'Width: ' . $settings['content_width'];
        }

        if ( isset( $settings['align'] ) ) {
            $summary[] = 'Align: ' . $settings['align'];
        }

        $background = $this->describe_background( $settings );
        if ( $background !== null ) {
            $summary[] = 'Background: ' . $background['type'];
        }

        return implode( ', ', $summary );
    }

    protected function describe_background( array $settings ): ?array {
        $type = $settings['background_background'] ?? '';

        if ( $type === 'video' ) {
            return array(
                'type'      => 'video',
                'has_media' => ! empty( $settings['background_video_link'] ),
            );
        }

        if ( $type === 'gradient' ) {
            return array(
                'type'      => 'gradient',
                'has_media' => false,
            );
        }

        if ( $type === 'classic' ) {
            if ( ! empty( $settings['background_image']['url'] ) ) {
                return array(
                    'type'      => 'image',
                    'has_media' => true,
                );
            }

            if ( ! empty( $settings['background_color'] ) ) {
                return array(
                    'type'      => 'color',
                    'has_media' => false,
                );
            }
        }

        return null;
    }

    protected function get_content_preview( array $element ): string {
        $widget_type = $element['widgetType'] ?? '';
        $settings    = $element['settings'] ?? array();

        switch ( $widget_type ) {
            case 'heading':
                return $settings['title'] ?? '';
            case 'text-editor':
                $editor = $settings['editor'] ?? '';

                return wp_strip_all_tags( $editor );
            case 'button':
                return $settings['text'] ?? '';
            case 'image':
                return $settings['image']['url'] ?? '';
            default:
                return '';
        }
    }

    protected function get_kit_data( int $kit_id, bool $include_colors, bool $include_typography, bool $include_settings ): array {
        $kit_post = get_post( $kit_id );
        if ( ! $kit_post ) {
            return array(
                'success'    => false,
                'error_code' => 'KIT_NOT_FOUND',
                /* translators: %s: kit id  */
                'message'    => sprintf( __( 'Kit with ID %s not found', 'hostinger-ai-assistant' ), $kit_id ),
            );
        }

        $template_type = get_post_meta( $kit_id, '_elementor_template_type', true );
        if ( $template_type !== 'kit' ) {
            return array(
                'success'    => false,
                'error_code' => 'NOT_A_KIT',
                /* translators: %1$s: kit id, %2$s template type  */
                'message'    => sprintf( __( 'Post %1$s is not an Elementor kit (template type: %2$s)', 'hostinger-ai-assistant' ), $kit_id, $template_type ),
            );
        }

        $kit_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
        if ( ! is_array( $kit_settings ) ) {
            $kit_settings = array();
        }

        $response = array(
            'kit_id'    => $kit_id,
            'kit_title' => $kit_post->post_title,
            'is_active' => ( (int) get_option( 'elementor_active_kit' ) === $kit_id ),
        );

        if ( $include_colors ) {
            $response['colors'] = $this->extract_kit_colors( $kit_settings );
        }

        if ( $include_typography ) {
            $response['typography'] = $this->extract_kit_typography( $kit_settings );
        }

        if ( $include_settings ) {
            $other_settings = $kit_settings;
            unset( $other_settings['system_colors'] );
            unset( $other_settings['custom_colors'] );
            unset( $other_settings['system_typography'] );
            unset( $other_settings['custom_typography'] );

            $response['settings'] = $other_settings;
        }

        return $response;
    }

    protected function extract_kit_colors( array $kit_settings ): array {
        $colors = array(
            'system' => array(),
            'custom' => array(),
        );

        $system_colors = $kit_settings['system_colors'] ?? null;
        if ( is_array( $system_colors ) ) {
            $colors['system'] = $this->parse_colors( $system_colors );
        }

        $custom_colors = $kit_settings['custom_colors'] ?? null;
        if ( is_array( $custom_colors ) ) {
            $colors['custom'] = $this->parse_colors( $custom_colors );
        }

        return $colors;
    }

    protected function parse_colors( array $colors_array ): array {
        $parsed = array();
        foreach ( $colors_array as $color ) {
            if ( is_array( $color ) ) {
                $parsed[] = array(
                    'id'    => $color['_id'] ?? '',
                    'title' => $color['title'] ?? '',
                    'color' => $color['color'] ?? '',
                );
            }
        }

        return $parsed;
    }

    protected function extract_kit_typography( array $kit_settings ): array {
        $typography = array(
            'system' => array(),
            'custom' => array(),
        );

        if ( isset( $kit_settings['system_typography'] ) && is_array( $kit_settings['system_typography'] ) ) {
            foreach ( $kit_settings['system_typography'] as $preset ) {
                if ( is_array( $preset ) ) {
                    $typography['system'][] = $this->format_typography_preset( $preset );
                }
            }
        }

        if ( isset( $kit_settings['custom_typography'] ) && is_array( $kit_settings['custom_typography'] ) ) {
            foreach ( $kit_settings['custom_typography'] as $preset ) {
                if ( is_array( $preset ) ) {
                    $typography['custom'][] = $this->format_typography_preset( $preset );
                }
            }
        }

        return $typography;
    }

    protected function format_typography_preset( array $preset ): array {
        $formatted = array(
            'id'    => $preset['_id'] ?? '',
            'title' => $preset['title'] ?? '',
        );

        $typography_props = array(
            'typography_font_family',
            'typography_font_weight',
            'typography_font_size',
            'typography_line_height',
            'typography_letter_spacing',
            'typography_text_transform',
            'typography_font_style',
            'typography_text_decoration',
        );

        foreach ( $typography_props as $prop ) {
            if ( isset( $preset[ $prop ] ) ) {
                $clean_key               = str_replace( 'typography_', '', $prop );
                $formatted[ $clean_key ] = $preset[ $prop ];
            }
        }

        return $formatted;
    }
}
