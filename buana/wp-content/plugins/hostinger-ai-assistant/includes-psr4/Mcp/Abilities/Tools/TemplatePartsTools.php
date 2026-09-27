<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class TemplatePartsTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/template-parts-search',
                    'label'       => __( 'List Template Parts', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a list of template parts.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'List Template Parts',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai/template-part-get',
                    'label'       => __( 'Get Template Part', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single template part by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get Template Part',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/template-part-update',
                    'label'       => __( 'Update Template Part', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update a template part content or title.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Template Part',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai/template-part-delete',
                    'label'       => __( 'Delete Template Part', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a template part by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Template Part',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WP_REST_Templates_Controller',
            '/wp/v2/template-parts',
            'wp_template_part'
        );

        $this->register_operations(
            array(
                'update' => array(
                    'tool_name'                  => 'hostinger-ai/template-part-assign-navigation',
                    'label'                      => __( 'Assign Navigation To Template Part', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Assign a navigation to a template part by providing template_part_id and navigation_id.', 'hostinger-ai-assistant' ),
                    'skip_ids'                   => true,
                    'input_schema_modifications' => array(
                        'properties' => array(
                            'template_part_id' => array(
                                'type'        => 'integer',
                                'description' => __( 'Template part ID.', 'hostinger-ai-assistant' ),
                            ),
                            'navigation_id'    => array(
                                'type'        => 'integer',
                                'description' => __( 'Navigation (wp_navigation) post ID.', 'hostinger-ai-assistant' ),
                            ),
                        ),
                        'required'   => array( 'template_part_id', 'navigation_id' ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Assign Navigation',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WP_REST_Templates_Controller',
            '/wp/v2/template-parts',
            'wp_template_part'
        );
    }
}
