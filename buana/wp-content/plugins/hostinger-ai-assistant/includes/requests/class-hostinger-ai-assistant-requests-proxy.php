<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Hostinger_Ai_Assistant_Requests_Proxy {
    private Hostinger_Ai_Assistant_Requests_Client $client;
    private array $default_headers;

    public function __construct( Hostinger_Ai_Assistant_Requests_Client $client, array $default_headers ) {
        $this->client          = $client;
        $this->default_headers = $default_headers;
    }

    private function get_software_id(): string {
        $endpoint = '/installations';
        $params   = array(
            'domain' => $this->default_headers['X-Hpanel-Domain'] ?? '',
        );
        $response = $this->client->get( $endpoint, $params );
        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( $response->get_error_message() );
            }
            return '';
        }

        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Error getting WP API Proxy Software ID' );
            }
            return '';
        }

        $body     = wp_remote_retrieve_body( $response );
        $response = json_decode( $body, true );

        return $response['data'][0]['id'] ?? '';
    }

    public function get( $endpoint, $params = array(), $headers = array() ): mixed {
        $api_endpoint = $this->parse_endpoint( $endpoint );
        return $this->client->get( $api_endpoint, $params, $headers );
    }

    public function post( string $endpoint, array $params, $headers = array() ): mixed {
        $api_endpoint = $this->parse_endpoint( $endpoint );
        return $this->client->post( $api_endpoint, $params, $headers );
    }

    private function parse_endpoint( $endpoint ): string {
        return str_replace( '{software_id}', $this->get_software_id(), $endpoint );
    }
}
