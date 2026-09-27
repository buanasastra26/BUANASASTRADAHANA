<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\WpHelper\Requests\Client;
use Hostinger\AiTheme\Builder\DebugMode;

defined( 'ABSPATH' ) || exit;

class RequestClient {
    /**
     * @var Client
     */
    private Client $client;

    /**
     * @param Client $client
     */
    public function __construct( Client $client ) {
        $this->client         = $client;
    }

    /**
     * @param string $endpoint
     * @param array  $params
     * @param array  $headers
     * @param int    $timeout
     * @param array  $args
     *
     * @return array
     */
    public function post( string $endpoint, array $params = array(), array $headers = array(), int $timeout = 120, array $args = array( 'data_format' => 'body' ) ): array
    {
        $response = $this->client->post( $endpoint, json_encode( $params ), $headers, $timeout, $args );

        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log('Something went wrong querying post ' . $endpoint . ' ' . $response->get_error_message() );
            }
        }

        $decoded_response = $this->decode_response($response);

        if ( DebugMode::is_enabled() ) {
            DebugMode::record( $endpoint, (int) $decoded_response['response_code'], (string) $decoded_response['response_body'] );
        }

        $response_data    = $decoded_response['response_data']['data'] ?? null;

        if ( empty( $response_data ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log('Response data: ');
                error_log(print_r($decoded_response, true));
                error_log(print_r($endpoint, true));
                error_log(print_r(json_encode( $params ), true));
                error_log(print_r($params, true));
            }
            return array();
        }

        return $response_data;
    }

    public function delete( string $endpoint, array $params = array(), array $headers = array(), int $timeout = 120 ): array
    {
        $url          = $this->client->get_api_url() . $endpoint;
        $request_args = array(
            'method'  => 'DELETE',
            'timeout' => $timeout,
            'headers' => array_merge( $this->client->get_default_headers(), $headers ),
        );

        if ( ! empty( $params ) ) {
            $request_args['body'] = json_encode( $params );
        }

        $response = wp_remote_request( $url, $request_args );

        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Something went wrong querying delete ' . $endpoint . ' ' . $response->get_error_message() );
            }
        }

        $decoded_response = $this->decode_response( $response );

        if ( DebugMode::is_enabled() ) {
            DebugMode::record( $endpoint, (int) $decoded_response['response_code'], (string) $decoded_response['response_body'] );
        }

        return $decoded_response['response_data']['data'] ?? array();
    }

    public function get( string $endpoint, array $params = array(), array $headers = array(), int $timeout = 120 ): array
    {
        $response = $this->client->get( $endpoint, $params, $headers, $timeout );

        if ( is_wp_error( $response ) ) {
            error_log('Something went wrong querying get ' . $endpoint . ' ' . $response->get_error_message() );
        }

        $decoded_response = $this->decode_response($response);

        if ( DebugMode::is_enabled() ) {
            DebugMode::record( $endpoint, (int) $decoded_response['response_code'], (string) $decoded_response['response_body'] );
        }

        $response_data    = $decoded_response['response_data']['data'] ?? null;

        if ( empty( $response_data ) ) {
            error_log('Response data: ');
            error_log(print_r($decoded_response, true));
            error_log(print_r($endpoint, true));
            return array();
        }

        return $response_data;
    }

    /**
     * @param array|\WP_Error $response
     *
     * @return array
     */
    public function decode_response( array|\WP_Error $response ): array
    {
        $response_body = wp_remote_retrieve_body( $response );
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_data = json_decode( $response_body, true);

        if ( !is_array( $response_data ) ) {
            $response_data = [ 'data' => null ];
        }

        return [
            'response_code' => $response_code,
            'response_data' => $response_data,
            'response_body' => $response_body,
        ];
    }
}
