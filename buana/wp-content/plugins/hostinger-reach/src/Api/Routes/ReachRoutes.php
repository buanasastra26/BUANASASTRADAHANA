<?php

namespace Hostinger\Reach\Api\Routes;

use Hostinger\Reach\Api\ApiKeyManager;
use Hostinger\Reach\Api\Handlers\ReachApiHandler;
use Hostinger\Reach\Repositories\ContactListRepository;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ReachRoutes extends Routes {
    private ReachApiHandler $handler;
    private ApiKeyManager $api_key_manager;

    private ContactListRepository $contact_list_repository;

    public function __construct( ReachApiHandler $handler, ApiKeyManager $api_key_manager, ContactListRepository $contact_list_repository ) {
        $this->handler                 = $handler;
        $this->api_key_manager         = $api_key_manager;
        $this->contact_list_repository = $contact_list_repository;
    }

    public function register_routes(): void {
        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'contact',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->handler, 'post_contact_handler' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'id'       => array(
                        'required' => true,
                        'type'     => 'string',
                    ),
                    'group'    => array(
                        'required'          => false,
                        'default'           => HOSTINGER_REACH_DEFAULT_CONTACT_LIST,
                        'type'              => 'string',
                        'validate_callback' => array( $this, 'is_valid_contact_list' ),
                    ),
                    'tags'     => array(
                        'required' => false,
                        'default'  => HOSTINGER_REACH_DEFAULT_CONTACT_LIST,
                        'type'     => 'string',
                    ),
                    'email'    => array(
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => function ( $param ) {
                            return filter_var( $param, FILTER_VALIDATE_EMAIL );
                        },
                    ),
                    'name'     => array(
                        'required' => false,
                        'default'  => '',
                        'type'     => 'string',
                    ),
                    'surname'  => array(
                        'required' => false,
                        'default'  => '',
                        'type'     => 'string',
                    ),
                    'metadata' => array(
                        'required'   => false,
                        'type'       => 'object',
                        'properties' => array(
                            'plugin' => array(
                                'type' => 'string',
                            ),
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'token',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->handler, 'post_token_handler' ),
                'permission_callback' => function ( WP_REST_Request $request ) {
                    $csrf = $request->get_param( 'csrf_field' );

                    return $csrf && $this->api_key_manager->validate_csrf( $csrf );
                },
                'args'                => array(
                    'csrf_field' => array(
                        'required' => true,
                        'type'     => 'string',
                    ),
                    'token'      => array(
                        'required' => true,
                        'type'     => 'string',
                    ),
                ),
            )
        );

        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'connect',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->handler, 'post_connect_handler' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'generate-auth-url',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->handler, 'post_generate_auth_url' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'connection-success',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this->handler, 'get_connection_success_handler' ),
                    'permission_callback' => array( $this, 'permission_check' ),
                ),
                array(
                    'methods'             => 'DELETE',
                    'callback'            => array( $this->handler, 'delete_connection_success_handler' ),
                    'permission_callback' => array( $this, 'permission_check' ),
                ),
            )
        );

        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'overview',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->handler, 'get_overview_handler' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'tags',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this->handler, 'get_tags_handler' ),
                    'permission_callback' => array( $this, 'permission_check' ),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this->handler, 'post_tags_handler' ),
                    'permission_callback' => array( $this, 'permission_check' ),
                    'args'                => array(
                        'names' => array(
                            'type'     => 'array',
                            'required' => true,
                            'default'  => array( HOSTINGER_REACH_DEFAULT_CONTACT_LIST ),
                            'items'    => array(
                                'type' => 'string',
                            ),
                        ),
                    ),
                ),

            )
        );

        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'builder-forms',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->handler, 'get_forms_handler' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        register_rest_route(
            HOSTINGER_REACH_PLUGIN_REST_API_BASE,
            'builder-form-preview/(?P<id>[a-zA-Z0-9\-]+)',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->handler, 'get_form_preview_handler' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'id' => array(
                        'required' => true,
                        'type'     => 'string',
                    ),
                ),
            )
        );
    }

    public function is_valid_contact_list( string $param ): bool {
        return $param === '' || $this->contact_list_repository->exists( $param );
    }
}
