<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class WooCouponsTools extends RestEndpointTool {
    public function register(): void {
        if ( ! $this->is_woocommerce_active() ) {
            return;
        }

        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/wc-coupons-search',
                    'label'       => __( 'Search WooCommerce Coupons', 'hostinger-ai-assistant' ),
                    'description' => __( 'Search and filter WooCommerce coupons with pagination. Returns a list of coupons matching the search criteria.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Search Coupons',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai/wc-coupons-get',
                    'label'       => __( 'Get WooCommerce Coupon', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single WooCommerce coupon by ID. Returns the full coupon object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get Coupon',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/wc-coupons-create',
                    'label'       => __( 'Create WooCommerce Coupon', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WooCommerce coupon. Requires a coupon code and discount details.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Coupon',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/wc-coupons-update',
                    'label'       => __( 'Update WooCommerce Coupon', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WooCommerce coupon by ID. Only provided fields will be updated.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Coupon',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai/wc-coupons-delete',
                    'label'       => __( 'Delete WooCommerce Coupon', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a WooCommerce coupon by ID. This action cannot be undone.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Coupon',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WC_REST_Coupons_Controller',
            '/wc/v3/coupons',
            'shop_coupon'
        );

        $this->register_operations(
            array(
                'batch' => array(
                    'tool_name'   => 'hostinger-ai/wc-coupons-batch',
                    'label'       => __( 'Batch Update WooCommerce Coupons', 'hostinger-ai-assistant' ),
                    'description' => __( 'Batch create, update, and delete WooCommerce coupons in a single request.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Batch Coupons',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WC_REST_Coupons_Controller',
            '/wc/v3/coupons/batch',
            'shop_coupon'
        );
    }

    protected function is_woocommerce_active(): bool {
        return class_exists( 'WooCommerce' );
    }
}
