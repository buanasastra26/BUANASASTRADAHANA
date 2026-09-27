<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class WooOrdersTools extends RestEndpointTool {
    public function register(): void {
        if ( ! $this->is_woocommerce_active() ) {
            return;
        }

        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/wc-orders-search',
                    'label'       => __( 'Search WooCommerce Orders', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a list of WooCommerce orders with search and filter capabilities. Returns a list of orders matching the criteria.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Search Orders',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai/wc-orders-get',
                    'label'       => __( 'Get WooCommerce Order', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single WooCommerce order by ID. Returns the full order object with all fields.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get Order',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/wc-orders-create',
                    'label'       => __( 'Create WooCommerce Order', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new WooCommerce order. Provide customer and line items data as needed.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Order',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/wc-orders-update',
                    'label'       => __( 'Update WooCommerce Order', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing WooCommerce order by ID. Use this to change status (e.g., pending, processing, completed, refunded) and other editable fields.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Order',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai/wc-orders-delete',
                    'label'       => __( 'Delete WooCommerce Order', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a WooCommerce order by ID. This action cannot be undone.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Order',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WC_REST_Orders_Controller',
            '/wc/v3/orders',
            'shop_order'
        );

        $reports = array(
            array(
                'tool_name'   => 'hostinger-ai/wc-reports-coupons-totals',
                'label'       => __( 'Get WooCommerce Coupons Report', 'hostinger-ai-assistant' ),
                'description' => __( 'Get WooCommerce coupons totals report. Returns count of coupons by coupon type.', 'hostinger-ai-assistant' ),
                'route'       => '/wc/v3/reports/coupons/totals',
                'title'       => 'Get Coupons Report',
            ),
            array(
                'tool_name'   => 'hostinger-ai/wc-reports-customers-totals',
                'label'       => __( 'Get WooCommerce Customers Report', 'hostinger-ai-assistant' ),
                'description' => __( 'Get WooCommerce customers totals report. Returns statistics about customers.', 'hostinger-ai-assistant' ),
                'route'       => '/wc/v3/reports/customers/totals',
                'title'       => 'Get Customers Report',
            ),
            array(
                'tool_name'   => 'hostinger-ai/wc-reports-orders-totals',
                'label'       => __( 'Get WooCommerce Orders Report', 'hostinger-ai-assistant' ),
                'description' => __( 'Get WooCommerce orders totals report. Returns statistics about orders.', 'hostinger-ai-assistant' ),
                'route'       => '/wc/v3/reports/orders/totals',
                'title'       => 'Get Orders Report',
            ),
            array(
                'tool_name'   => 'hostinger-ai/wc-reports-products-totals',
                'label'       => __( 'Get WooCommerce Products Report', 'hostinger-ai-assistant' ),
                'description' => __( 'Get WooCommerce products totals report. Returns statistics about products.', 'hostinger-ai-assistant' ),
                'route'       => '/wc/v3/reports/products/totals',
                'title'       => 'Get Products Report',
            ),
            array(
                'tool_name'   => 'hostinger-ai/wc-reports-reviews-totals',
                'label'       => __( 'Get WooCommerce Reviews Report', 'hostinger-ai-assistant' ),
                'description' => __( 'Get WooCommerce reviews totals report. Returns statistics about product reviews.', 'hostinger-ai-assistant' ),
                'route'       => '/wc/v3/reports/reviews/totals',
                'title'       => 'Get Reviews Report',
            ),
            array(
                'tool_name'                  => 'hostinger-ai/wc-reports-sales',
                'label'                      => __( 'Get WooCommerce Sales Report', 'hostinger-ai-assistant' ),
                'description'                => __( 'Get WooCommerce sales report. Returns detailed sales statistics and revenue data.', 'hostinger-ai-assistant' ),
                'route'                      => '/wc/v3/reports/sales',
                'title'                      => 'Get Sales Report',
                'input_schema_modifications' => array(
                    'properties' => array(
                        'period'   => array(
                            'type'        => 'string',
                            'description' => __( 'Report period. Options: week, month, last_month, year.', 'hostinger-ai-assistant' ),
                            'enum'        => array( 'week', 'month', 'last_month', 'year' ),
                        ),
                        'date_min' => array(
                            'type'        => 'string',
                            'description' => __( 'Return sales for a specific start date. Format: YYYY-MM-DD.', 'hostinger-ai-assistant' ),
                        ),
                        'date_max' => array(
                            'type'        => 'string',
                            'description' => __( 'Return sales for a specific end date. Format: YYYY-MM-DD.', 'hostinger-ai-assistant' ),
                        ),
                    ),
                ),
            ),
        );

        foreach ( $reports as $report ) {
            $report_config = array(
                'tool_name'   => $report['tool_name'],
                'label'       => $report['label'],
                'description' => $report['description'],
                'meta'        => array(
                    'annotations' => array(
                        'title'         => $report['title'],
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            );

            if ( ! empty( $report['input_schema_modifications'] ) ) {
                $report_config['input_schema_modifications'] = $report['input_schema_modifications'];
            }

            $this->register_operations(
                array(
                    'report' => $report_config,
                ),
                'WC_REST_Orders_Controller',
                $report['route'],
                'shop_order'
            );
        }
    }

    private function is_woocommerce_active(): bool {
        return class_exists( 'WooCommerce' );
    }
}
