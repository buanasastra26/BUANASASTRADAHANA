<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class MenusTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/menus-search',
                    'label'       => __( 'List Menus', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a list of navigation menus.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'List Menus',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai/menus-get',
                    'label'       => __( 'Get Menu', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single navigation menu by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get Menu',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/menus-create',
                    'label'       => __( 'Create Menu', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new navigation menu.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Menu',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/menus-update',
                    'label'       => __( 'Update Menu', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing navigation menu by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Menu',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'                  => 'hostinger-ai/menus-delete',
                    'label'                      => __( 'Delete Menu', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Delete a navigation menu by ID.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'force' => array(
                                'type'        => 'boolean',
                                'description' => __( 'Force menu item deletion', 'hostinger-ai-assistant' ),
                            ),
                        ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Delete Menu',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WP_REST_Menus_Controller',
            '/wp/v2/menus',
            'nav_menu'
        );

        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/menu-items-search',
                    'label'       => __( 'List Menu Items', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a list of menu items with filtering options.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'List Menu Items',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai/menu-items-get',
                    'label'       => __( 'Get Menu Item', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single menu item by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get Menu Item',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/menu-items-create',
                    'label'       => __( 'Create Menu Item', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new menu item. Provide title, url or object_id/object, and menu (menu_id).', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Menu Item',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/menu-items-update',
                    'label'       => __( 'Update Menu Item', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing menu item, including label, URL, parent, and order.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Menu Item',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai/menu-items-delete',
                    'label'       => __( 'Delete Menu Item', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a menu item by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Menu Item',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WP_REST_Menu_Items_Controller',
            '/wp/v2/menu-items',
            'nav_menu_item'
        );

        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/menu-locations-list',
                    'label'       => __( 'List Menu Locations', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a list of theme menu locations and their assigned menus.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'List Menu Locations',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                    'skip_ids'    => true,
                ),
                'update' => array(
                    'tool_name'                  => 'hostinger-ai/menu-locations-assign',
                    'label'                      => __( 'Assign Menu To Location', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Assign a menu to a given theme location (e.g., primary). Provide location and menu ID.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'properties' => array(
                            'location' => array(
                                'type'        => 'string',
                                'description' => __( 'Theme location slug (e.g., primary, header, footer).', 'hostinger-ai-assistant' ),
                            ),
                            'menu'     => array(
                                'type'        => 'integer',
                                'description' => __( 'Menu ID to assign to the location.', 'hostinger-ai-assistant' ),
                            ),
                        ),
                        'required'   => array( 'location', 'menu' ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Assign Menu Location',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WP_REST_Menu_Locations_Controller',
            '/wp/v2/menu-locations',
            'menu_location'
        );
    }
}
