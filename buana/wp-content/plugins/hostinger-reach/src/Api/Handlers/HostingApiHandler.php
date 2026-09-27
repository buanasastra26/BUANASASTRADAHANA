<?php

namespace Hostinger\Reach\Api\Handlers;

use Hostinger\WpHelper\Requests\Client;
use Hostinger\Reach\Functions;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class HostingApiHandler extends ApiHandler {

    private Client $client;

    public function __construct( Functions $functions, Client $client ) {
        parent::__construct( $functions );
        $this->client = $client;
    }

    public function get_domain_details_handler(): WP_REST_Response {
        // For non Hostinger Users prevent request and just return an empty array.
        if ( ! $this->get_functions()->is_hostinger_user() ) {
            return $this->handle_response(
                array(
                    'response' => array(
                        'code' => 200,
                    ),
                    'body'     => wp_json_encode( array() ),
                )
            );
        }

        $siteurl       = get_option( 'siteurl' );
        $domain        = apply_filters( 'hostinger_reach_domain', parse_url( $siteurl, PHP_URL_HOST ) );
        $transient_key = 'hostinger_reach_domain_details_' . md5( $domain );
        $disable_cache = apply_filters( 'hostinger_reach_disable_rest_cache', false );

        if ( ! $disable_cache ) {
            $cached_response = get_transient( $transient_key );
            if ( $cached_response !== false ) {
                return $this->handle_response( $cached_response );
            }
        }

        $response = $this->client->get( '/v3/wordpress/domain-details', array( 'domain' => $domain ) );

        if ( is_wp_error( $response ) ) {
            return $this->handle_wp_error( $response );
        }

        if ( ! $disable_cache ) {
            set_transient( $transient_key, $response, MINUTE_IN_SECONDS );
        }

        return $this->handle_response( $response );
    }
}
