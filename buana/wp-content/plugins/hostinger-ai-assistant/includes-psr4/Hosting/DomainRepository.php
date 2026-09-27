<?php

namespace Hostinger\AiAssistant\Hosting;

use Hostinger\WpHelper\Requests\Client;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class DomainRepository {
    private const DOMAIN_DETAILS_ENDPOINT = '/v3/wordpress/domain-details';

    public function __construct( private Client $client ) {
    }

    public function get_domain_details(): ?array {
        $domain   = (string) wp_parse_url( (string) get_option( 'siteurl' ), PHP_URL_HOST );
        $response = $this->client->get( self::DOMAIN_DETAILS_ENDPOINT, array( 'domain' => $domain ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) ) {
            return null;
        }

        return $body['data'] ?? $body;
    }
}
