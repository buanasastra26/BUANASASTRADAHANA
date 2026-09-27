<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_REST_Posts_Controller;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class PagesTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/pages-search',
                    'label'       => __( 'Search Pages', 'hostinger-ai-assistant' ),
                    'description' => __( 'Search and filter WordPress pages with pagination. Returns a list of pages matching the search criteria.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Search Pages',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai/pages-get',
                    'label'       => __( 'Get Page', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single WordPress page by ID. Returns the full page object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get Page',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/pages-create',
                    'label'       => __( 'Create Page', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WordPress page. Requires title and content. Write the content using plain HTML and, where possible, shortcodes rather than Gutenberg block markup (e.g. avoid "<!-- wp:... -->" comments). Prefer shortcodes over custom Gutenberg blocks so the page never references a block that is not registered on the site, which would break the editor. For a contact form use the [hostinger_contact_form] shortcode instead of a custom block. Only use a Gutenberg block if you are certain it is registered.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Page',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/pages-update',
                    'label'       => __( 'Update Page', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WordPress page by ID. Only provided fields will be updated. When updating content, write plain HTML and, where possible, shortcodes rather than Gutenberg block markup (e.g. avoid "<!-- wp:... -->" comments). Prefer shortcodes over custom Gutenberg blocks so the page never references a block that is not registered on the site. For a contact form use the [hostinger_contact_form] shortcode instead of a custom block.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Page',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai/pages-delete',
                    'label'       => __( 'Delete Page', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a WordPress page by ID. This action cannot be undone.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Page',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            WP_REST_Posts_Controller::class,
            '/wp/v2/pages',
            'page'
        );
    }
}
