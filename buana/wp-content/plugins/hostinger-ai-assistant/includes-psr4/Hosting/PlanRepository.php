<?php

namespace Hostinger\AiAssistant\Hosting;

use Hostinger\WpHelper\Requests\Client;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class PlanRepository {
    private const PLAN_DETAILS_ENDPOINT = '/v3/wordpress/plan-details';

    public function __construct( private Client $client ) {
    }

    /**
     * @return array|null The plan payload (e.g. ['hosting' => ['expires_at' => ...], ...]) or null on failure.
     */
    public function get_plan_details(): ?array {
        global $wpdb;

        $response = $this->client->get( self::PLAN_DETAILS_ENDPOINT, array( 'db_name' => $wpdb->dbname ) );

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
