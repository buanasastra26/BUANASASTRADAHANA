<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class CreateContainer extends ContainerTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-create-container',
            array(
                'label'               => __( 'Create Elementor Container', 'hostinger-ai-assistant' ),
                'description'         => __( 'Creates a new Elementor container (section) at root or inside a parent container. Can optionally create multiple inner columns.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array_merge(
                        $this->schema_post_id(),
                        $this->schema_parent_and_position(),
                        $this->schema_layout_settings(),
                        $this->schema_columns_simple()
                    ),
                    'required'   => array( 'post_id' ),
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
                        'title'           => 'Create Container',
                        'readOnlyHint'    => false,
                        'destructiveHint' => false,
                        'idempotentHint'  => false,
                        'openWorldHint'   => false,
                    ),
                ),
            )
        );
    }

    public function execute( array $input ): array {
        $post_id       = (int) $input['post_id'];
        $parent_id     = $input['parent_id'] ?? null;
        $position      = $input['position'] ?? 'end';
        $sibling_id    = $input['sibling_id'] ?? null;
        $index         = isset( $input['index'] ) ? (int) $input['index'] : null;
        $layout        = $input['layout'] ?? 'flex';
        $direction     = $input['direction'] ?? 'row';
        $content_width = $input['content_width'] ?? 'boxed';
        $columns       = isset( $input['columns'] ) ? max( 1, (int) $input['columns'] ) : 1;
        $css_class     = $input['css_class'] ?? '';

        $result = $this->get_elementor_data( $post_id );
        if ( isset( $result['error_code'] ) ) {
            return $result;
        }

        $elementor_data = $result['data'];

        $new_container = $this->make_container( $layout, $direction, $content_width, $css_class, $columns, ! empty( $parent_id ) );

        if ( empty( $parent_id ) ) {
            $insert_result = $this->insert_into_list( $elementor_data, $new_container, $position, $sibling_id, $index, $this->last_insert_index );
        } else {
            $insert_result = $this->update_widget_in_tree(
                $elementor_data,
                $parent_id,
                function ( &$parent_element ) use ( $new_container, $position, $sibling_id, $index ) {
                    if ( ! isset( $parent_element['elements'] ) || ! is_array( $parent_element['elements'] ) ) {
                        $parent_element['elements'] = array();
                    }

                    return $this->insert_into_list( $parent_element['elements'], $new_container, $position, $sibling_id, $index, $this->last_insert_index )
                        ? null
                        : array(
                            'error_code' => 'INSERT_FAILED',
                            'message'    => __( 'Failed to insert container into parent elements', 'hostinger-ai-assistant' ),
                        );
                }
            );

            if ( ! $insert_result ) {
                return array(
                    'success'    => false,
                    'error_code' => 'PARENT_NOT_FOUND',
                    /* translators: %s: parent id */
                    'message'    => sprintf( __( "Parent container with ID '%s' not found", 'hostinger-ai-assistant' ), $parent_id ),
                );
            }
        }

        if ( ! $insert_result ) {
            return array(
                'success'    => false,
                'error_code' => 'INSERT_FAILED',
                'message'    => __( 'Could not insert the new container at requested position', 'hostinger-ai-assistant' ),
            );
        }

        $save_result = $this->save_elementor_data( $post_id, $elementor_data );
        if ( ! $save_result['success'] ) {
            return $save_result;
        }

        return array(
            'success'         => true,
            'post_id'         => $post_id,
            'parent_id'       => $parent_id,
            'position'        => $position,
            'insert_index'    => $this->last_insert_index ?? null,
            'container_id'    => $new_container['id'],
            'created_columns' => $columns,
        );
    }

    private ?int $last_insert_index = null;
}
