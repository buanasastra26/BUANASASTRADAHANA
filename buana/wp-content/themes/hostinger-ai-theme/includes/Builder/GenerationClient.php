<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Constants\ApiRoutes;
use Hostinger\WpHelper\Requests\Client;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * RequestClient returns [] on failure, which looks like an empty in-progress generation.
 * This client returns HTTP status + body instead.
 */
class GenerationClient {

    public const START_TIMEOUT = 30;

    public const STATUS_TIMEOUT = 30;

    public const SUGGEST_PAGES_TIMEOUT = 60;

    private const START_PATH = '/content/generate-website';

    private const GENERATIONS_PATH = '/content/generations/';

    private const SUGGEST_PAGES_PATH = '/content/suggest-pages';

    private Client $client;

    public function __construct( Client $client ) {
        $this->client = $client;
    }

    public function start_generation( string $software_id, array $payload ): array {
        return $this->post( $this->installation_path( $software_id ) . self::START_PATH, $payload, self::START_TIMEOUT );
    }

    public function get_generation( string $software_id, string $generation_id ): array {
        $endpoint = $this->installation_path( $software_id ) . self::GENERATIONS_PATH . rawurlencode( $generation_id );

        return $this->get( $endpoint, self::STATUS_TIMEOUT );
    }

    public function suggest_pages( string $software_id, array $payload ): array {
        return $this->post(
            $this->installation_path( $software_id ) . self::SUGGEST_PAGES_PATH,
            $payload,
            self::SUGGEST_PAGES_TIMEOUT
        );
    }

    private function installation_path( string $software_id ): string {
        return ApiRoutes::INSTALLATIONS_BASE . $software_id;
    }

    private function post( string $endpoint, array $payload, int $timeout ): array {
        return $this->decode( $endpoint, $this->client->post( $endpoint, wp_json_encode( $payload ), array(), $timeout ) );
    }

    private function get( string $endpoint, int $timeout ): array {
        return $this->decode( $endpoint, $this->client->get( $endpoint, array(), array(), $timeout ) );
    }

    private function decode( string $endpoint, $response ): array {
        if ( is_wp_error( $response ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Generation proxy request failed ' . $endpoint . ' ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }

        if ( is_wp_error( $response ) ) {
            return array(
                'code' => 0,
                'body' => array(),
            );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $raw  = (string) wp_remote_retrieve_body( $response );

        if ( DebugMode::is_enabled() ) {
            DebugMode::record( $endpoint, $code, $raw );
        }

        $body = json_decode( $raw, true );

        return array(
            'code' => $code,
            'body' => is_array( $body ) ? $body : array(),
        );
    }
}
