<?php

namespace Hostinger\AiAssistant\Mcp;

use Hostinger\AiAssistant\Functions;
use Hostinger\AiAssistant\Mcp\Rest\JwtAuth;
use Hostinger\Amplitude\AmplitudeManager;
use Hostinger_Ai_Assistant_Amplitude_Actions;
use Throwable;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class McpConnectionTracker {
    private const AMPLITUDE_ENDPOINT = '/v3/wordpress/plugin/trigger-event';
    private const INITIALIZE_METHOD  = 'initialize';
    private const UNKNOWN_CLIENT     = 'unknown';

    private AmplitudeManager $amplitude_manager;
    private JwtAuth $jwt_auth;

    public function __construct( AmplitudeManager $amplitude_manager, JwtAuth $jwt_auth ) {
        $this->amplitude_manager = $amplitude_manager;
        $this->jwt_auth          = $jwt_auth;
    }

    public function init(): void {
        $current_url = $_SERVER['REQUEST_URI'] ?? '';

        if ( ! str_contains( $current_url, HOSTINGER_AI_ASSISTANT_REST_API_BASE . '/mcp' ) ) {
            return;
        }

        add_filter( 'rest_pre_dispatch', array( $this, 'maybe_track_initialize' ), 10, 3 );
    }

    public function maybe_track_initialize( mixed $result, WP_REST_Server $server, WP_REST_Request $request ): mixed {
        try {
            $body = $request->get_json_params();

            if ( ! is_array( $body ) || ( $body['method'] ?? '' ) !== self::INITIALIZE_METHOD ) {
                return $result;
            }

            $this->track_connection( $request, $body );
        } catch ( Throwable $e ) {
            return $result;
        }

        return $result;
    }


    private function track_connection( WP_REST_Request $request, array $body ): void {
        $params             = is_array( $body['params'] ?? null ) ? $body['params'] : array();
        $mcp_client_info    = is_array( $params['clientInfo'] ?? null ) ? $params['clientInfo'] : array();
        $mcp_client_name    = is_string( $mcp_client_info['name'] ?? null ) ? $mcp_client_info['name'] : null;
        $mcp_client_version = is_string( $mcp_client_info['version'] ?? null ) ? $mcp_client_info['version'] : null;
        $is_mcp_connector   = ! empty( $this->jwt_auth->get_token_from_request( $request ) );

        if ( ! $mcp_client_name ) {
            $user_agent      = $request->get_header( 'user_agent' );
            $mcp_client_name = ! empty( $user_agent ) ? $user_agent : self::UNKNOWN_CLIENT;
        }

        $args = array(
            'action'               => Hostinger_Ai_Assistant_Amplitude_Actions::MCP_CONNECTED,
            'mcp_client_name'      => $mcp_client_name,
            'mcp_client_version'   => $mcp_client_version,
            'mcp_is_mcp_connector' => $is_mcp_connector ? 'yes' : 'no',
        );

        $this->amplitude_manager->sendRequest(
            self::AMPLITUDE_ENDPOINT,
            $args
        );
    }
}
