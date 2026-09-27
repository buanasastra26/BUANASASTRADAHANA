<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class DeleteContainer extends ContainerTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-delete-container',
            array(
                'label'               => __( 'Delete Elementor Container', 'hostinger-ai-assistant' ),
                'description'         => __( 'Safely removes a container. Optionally preserves children by moving them to the parent at the same position. Can require confirmation for complex structures.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array_merge(
                        $this->schema_post_id(),
                        $this->schema_container_target(),
                        array(
                            'preserve_children' => array(
                                'type'        => 'boolean',
                                'description' => __( 'If true, move the container\'s children into the parent at the same position instead of deleting them.', 'hostinger-ai-assistant' ),
                                'default'     => false,
                            ),
                            'confirm'           => array(
                                'type'        => 'boolean',
                                'description' => __( 'Confirm deletion when the subtree is complex (many descendants) and children will be removed.', 'hostinger-ai-assistant' ),
                                'default'     => false,
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
                        'title'           => 'Delete Container',
                        'readOnlyHint'    => false,
                        'destructiveHint' => true,
                        'idempotentHint'  => true,
                        'openWorldHint'   => false,
                    ),
                ),
            )
        );
    }

    public function execute( array $input ): array {
        $post_id           = (int) ( $input['post_id'] ?? 0 );
        $container_id      = (string) ( $input['container_id'] ?? '' );
        $preserve_children = (bool) ( $input['preserve_children'] ?? false );
        $confirm           = (bool) ( $input['confirm'] ?? false );

        $result = $this->get_elementor_data( $post_id );
        if ( isset( $result['error_code'] ) ) {
            return $result;
        }

        $elementor_data = $result['data'];

        $summary            = array();
        $needs_confirmation = false;

        $deleted = $this->delete_element_in_tree( $elementor_data, $container_id, $preserve_children, $confirm, $summary, $needs_confirmation );

        if ( $needs_confirmation && ! $confirm ) {
            return array(
                'success'               => false,
                'error_code'            => 'CONFIRMATION_REQUIRED',
                'message'               => __( 'This deletion will remove a complex subtree. Set confirm=true to proceed or enable preserve_children to keep children.', 'hostinger-ai-assistant' ),
                'container_id'          => $container_id,
                'requires_confirmation' => true,
                'summary'               => $summary,
            );
        }

        if ( ! $deleted ) {
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
            'deleted'      => true,
            'preserved'    => $preserve_children,
            'summary'      => $summary,
        );
    }
}
