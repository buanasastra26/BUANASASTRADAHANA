<?php

namespace Hostinger\Reach\Api\Routes;

use Hostinger\Reach\Api\Handlers\HostingApiHandler;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class HostingRoutes extends Routes {
    private HostingApiHandler $handler;

    public function __construct( HostingApiHandler $handler ) {
        $this->handler = $handler;
    }

    public function register_routes(): void {
        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'hosting/domain-details',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->handler, 'get_domain_details_handler' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );
    }
}
