<?php

namespace Hostinger\AiTheme\Builder;

use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class DebugMode {

    private const NAMESPACE = 'hostinger-ai-plugin/v1';

    private static array $entries = array();

    public function register(): void {
        add_filter( 'rest_post_dispatch', array( $this, 'append_debug_info' ), 10, 3 );
    }

    public static function is_enabled(): bool {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        return defined( 'WP_DEBUG' ) && WP_DEBUG;
    }

    public static function record( string $endpoint, int $status_code, string $response_body ): void {
        self::$entries[] = array(
            'endpoint'      => $endpoint,
            'status_code'   => $status_code,
            'response_body' => $response_body,
        );
    }

    public static function get_entries(): array {
        return self::$entries;
    }

    public static function reset(): void {
        self::$entries = array();
    }

    public function append_debug_info( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response || ! $request instanceof WP_REST_Request ) {
            return $response;
        }

        $namespace = defined( 'HOSTINGER_AI_WEBSITES_REST_API_BASE' ) ? HOSTINGER_AI_WEBSITES_REST_API_BASE : self::NAMESPACE;

        if ( strpos( (string) $request->get_route(), '/' . $namespace . '/' ) === false ) {
            return $response;
        }

        if ( empty( self::$entries ) ) {
            return $response;
        }

        $data = $response->get_data();
        if ( is_array( $data ) ) {
            $data['debug_info'] = self::$entries;
            $response->set_data( $data );
        }

        return $response;
    }
}
