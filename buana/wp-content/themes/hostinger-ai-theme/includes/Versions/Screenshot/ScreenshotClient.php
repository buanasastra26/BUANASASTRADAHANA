<?php

namespace Hostinger\AiTheme\Versions\Screenshot;

use Hostinger\AiTheme\Builder\DebugMode;
use Hostinger\AiTheme\Constants\ApiRoutes;
use Hostinger\WpHelper\Requests\Client;

defined( 'ABSPATH' ) || exit;

class ScreenshotClient {
    public const REQUEST_TIMEOUT = 10;
    public const FETCH_TIMEOUT = 30;
    private const SCREENSHOT_PATH = '/screenshot';
    private const REDACT_KEYS = array( 'image', 'screenshot', 'data' );

    private Client $client;

    public function __construct( Client $client ) {
        $this->client = $client;
    }

    public function request( string $software_id ): array {
        $endpoint = $this->endpoint( $software_id );

        return $this->decode(
            $endpoint,
            $this->client->post( $endpoint, wp_json_encode( array() ), array(), self::REQUEST_TIMEOUT )
        );
    }

    public function fetch( string $software_id ): array {
        $endpoint = $this->endpoint( $software_id );

        return $this->decode( $endpoint, $this->client->get( $endpoint, array(), array(), self::FETCH_TIMEOUT ) );
    }

    private function endpoint( string $software_id ): string {
        return ApiRoutes::INSTALLATIONS_BASE . $software_id . self::SCREENSHOT_PATH;
    }

    private function decode( string $endpoint, $response ): array {
        if ( is_wp_error( $response ) ) {
            if ( DebugMode::is_enabled() ) {
                DebugMode::record( $endpoint, 0, $response->get_error_message() );
            }

            return array(
                'code' => 0,
                'data' => array(),
            );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $raw  = (string) wp_remote_retrieve_body( $response );
        $body = json_decode( $raw, true );
        $data = is_array( $body ) && is_array( $body['data'] ?? null ) ? $body['data'] : array();

        if ( DebugMode::is_enabled() ) {
            DebugMode::record( $endpoint, $code, $this->redact( $data, $raw ) );
        }

        return array(
            'code' => $code,
            'data' => $data,
        );
    }

    private function redact( array $data, string $raw ): string {
        if ( empty( $data ) ) {
            return $raw;
        }

        foreach ( self::REDACT_KEYS as $key ) {
            if ( ! empty( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
                $data[ $key ] = sprintf( '<base64 %d bytes>', strlen( $data[ $key ] ) );
            }
        }

        return (string) wp_json_encode( array( 'data' => $data ) );
    }
}
