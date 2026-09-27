<?php

namespace Hostinger\Reach\Api\Routes;

use Hostinger\Reach\Api\Handlers\IntegrationsApiHandler;
use Hostinger\Reach\Integrations\Integration;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class IntegrationsRoutes extends Routes {

    private IntegrationsApiHandler $handler;

    public function __construct( IntegrationsApiHandler $handler ) {
        $this->handler = $handler;
    }

    public function register_routes(): void {
        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'integrations',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->handler, 'get_integrations_handler' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'status',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->handler, 'get_status_handler' ),
                'permission_callback' => array( $this, 'public_permission_check' ),
            )
        );

        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'integrations',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->handler, 'post_integrations_handler' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'integration'                      => array(
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => function ( $param ) {
                            return array_key_exists( $param, IntegrationsApiHandler::get_integrations() );
                        },
                    ),
                    Integration::INTEGRATION_IS_ACTIVE => array(
                        'required' => true,
                        'type'     => 'boolean',
                    ),
                ),
            )
        );

        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'import/(?P<integration>[a-z0-9-]+)',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->handler, 'get_import_handler' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'integration' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => function ( $param ) {
                            return array_key_exists( $param, IntegrationsApiHandler::get_integrations() );
                        },
                    ),
                ),
            )
        );

        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'import',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->handler, 'post_import_handler' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'integrations' => array(
                        'required'          => true,
                        'type'              => 'object',
                        'validate_callback' => function ( $param ) {
                            foreach ( array_keys( $param ) as $integration ) {
                                if ( ! array_key_exists( $integration, IntegrationsApiHandler::get_integrations() ) ) {
                                    return false;
                                }
                            }

                            // Validate if the value structure is an array.
                            foreach ( $param as $ids ) {
                                if ( ! is_array( $ids ) ) {
                                    return false;
                                }
                            }

                            return true;
                        },
                    ),
                ),
            )
        );
    }
}
