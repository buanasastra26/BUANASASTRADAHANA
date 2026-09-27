<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class WooProductsTools extends RestEndpointTool {
    public function register(): void {
        if ( ! $this->is_woocommerce_active() ) {
            return;
        }

        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/wc-products-search',
                    'label'       => __( 'Search WooCommerce Products', 'hostinger-ai-assistant' ),
                    'description' => __( 'Search and filter WooCommerce products with pagination. Returns a list of products matching the search criteria.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Search Products',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai/wc-products-get',
                    'label'       => __( 'Get WooCommerce Product', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single WooCommerce product by ID. Returns the full product object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get Product',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/wc-products-create',
                    'label'       => __( 'Create WooCommerce Product', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WooCommerce product. Requires name and other product details.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Product',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/wc-products-update',
                    'label'       => __( 'Update WooCommerce Product', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WooCommerce product by ID. Only provided fields will be updated.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Product',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai/wc-products-delete',
                    'label'       => __( 'Delete WooCommerce Product', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a WooCommerce product by ID. This action cannot be undone.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Product',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WC_REST_Products_Controller',
            '/wc/v3/products',
            'product'
        );

        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/wc-product-categories-list',
                    'label'       => __( 'List WooCommerce Product Categories', 'hostinger-ai-assistant' ),
                    'description' => __( 'List all WooCommerce product categories. Returns a list of product categories.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'List Product Categories',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/wc-product-categories-create',
                    'label'       => __( 'Create WooCommerce Product Category', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WooCommerce product category. Requires name.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Product Category',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/wc-product-categories-update',
                    'label'       => __( 'Update WooCommerce Product Category', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WooCommerce product category by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Product Category',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'                  => 'hostinger-ai/wc-product-categories-delete',
                    'label'                      => __( 'Delete WooCommerce Product Category', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Delete a WooCommerce product category by ID.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'force' => array(
                                'type'        => 'boolean',
                                'description' => __( 'Force category deletion', 'hostinger-ai-assistant' ),
                            ),
                        ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Delete Product Category',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WC_REST_Product_Categories_Controller',
            '/wc/v3/products/categories',
            'product_cat'
        );

        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/wc-product-tags-list',
                    'label'       => __( 'List WooCommerce Product Tags', 'hostinger-ai-assistant' ),
                    'description' => __( 'List all WooCommerce product tags. Returns a list of product tags.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'List Product Tags',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/wc-product-tags-create',
                    'label'       => __( 'Create WooCommerce Product Tag', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WooCommerce product tag. Requires name.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Product Tag',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/wc-product-tags-update',
                    'label'       => __( 'Update WooCommerce Product Tag', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WooCommerce product tag by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Product Tag',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'                  => 'hostinger-ai/wc-product-tags-delete',
                    'label'                      => __( 'Delete WooCommerce Product Tag', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Delete a WooCommerce product tag by ID.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'force' => array(
                                'type'        => 'boolean',
                                'description' => __( 'Force tag deletion', 'hostinger-ai-assistant' ),
                            ),
                        ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Delete Product Tag',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WC_REST_Product_Tags_Controller',
            '/wc/v3/products/tags',
            'product_tag'
        );

        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/wc-product-brands-list',
                    'label'       => __( 'List WooCommerce Product Brands', 'hostinger-ai-assistant' ),
                    'description' => __( 'List all WooCommerce product brands. Returns a list of product brands.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'List Product Brands',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/wc-product-brands-create',
                    'label'       => __( 'Create WooCommerce Product Brand', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WooCommerce product brand. Requires name.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Product Brand',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/wc-product-brands-update',
                    'label'       => __( 'Update WooCommerce Product Brand', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WooCommerce product brand by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Product Brand',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'                  => 'hostinger-ai/wc-product-brands-delete',
                    'label'                      => __( 'Delete WooCommerce Product Brand', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Delete a WooCommerce product brand by ID.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'force' => array(
                                'type'        => 'boolean',
                                'description' => __( 'Force Product Brand deletion', 'hostinger-ai-assistant' ),
                            ),
                        ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Delete Product Brand',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WC_REST_Product_Brands_Controller',
            '/wc/v3/products/brands',
            'product_brand'
        );
    }

    protected function is_woocommerce_active(): bool {
        return class_exists( 'WooCommerce' );
    }
}
