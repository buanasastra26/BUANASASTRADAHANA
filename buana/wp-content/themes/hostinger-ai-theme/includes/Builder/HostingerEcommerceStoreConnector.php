<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Data\HostingerEcommerceHelper;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

class HostingerEcommerceStoreConnector {
    private const ATTACH_CHANNEL_ROUTE = '/hostinger-ecommerce/v1/attach-channel';

    private HostingerEcommerceBuilder $ecommerce_builder;

    public function __construct( HostingerEcommerceBuilder $ecommerce_builder ) {
        $this->ecommerce_builder = $ecommerce_builder;
    }

    public function init(): void {
        add_action( 'admin_init', array( $this, 'maybe_connect' ) );
    }

    public function maybe_connect(): void {
        if ( ! HostingerEcommerceHelper::needs_store_connection() ) {
            return;
        }

        if ( ! $this->ecommerce_builder->is_plugin_active() ) {
            return;
        }

        $this->connect();
    }

    private function connect(): void {
        HostingerEcommerceHelper::set_is_connecting();

        $request = new WP_REST_Request( 'POST', self::ATTACH_CHANNEL_ROUTE );
        $request->set_param( 'store_id', HostingerEcommerceHelper::get_store_id() );

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            Helper::log( 'Hostinger Ecommerce store connect failed: ' . $response->as_error()->get_error_message() );

            return;
        }

        HostingerEcommerceHelper::clear_is_connecting();
    }
}
