<?php

namespace Hostinger\AiAssistant\Mcp;

use Hostinger\AiAssistant\Functions;
use Hostinger\AiAssistant\Mcp\Abilities\AbilityCatalog;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Discovery\DiscoverAbilitiesTool;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Discovery\ExecuteAbilityTool;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Discovery\GetAbilityInfoTool;
use Hostinger\AiAssistant\Mcp\Rest\JwtAuth;
use Throwable;
use WP\MCP\Core\McpAdapter;
use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use WP\MCP\Transport\HttpTransport;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class McpServer {
    public const MCP_SERVER_VERSION         = '1.0.0';
    private const DEFAULT_SERVER_CAPABILITY = 'manage_options';
    private JwtAuth $jwt_auth;

    public function __construct( JwtAuth $jwt_auth ) {
        $this->jwt_auth = $jwt_auth;
    }
    public function init(): void {
        add_filter( 'mcp_adapter_default_transport_permission_user_capability', array( $this, 'get_default_server_capability' ) );
        add_filter( 'mcp_adapter_discover_abilities_capability', array( $this, 'get_default_server_capability' ) );
        add_filter( 'mcp_adapter_get_ability_info_capability', array( $this, 'get_default_server_capability' ) );
        add_filter( 'mcp_adapter_execute_ability_capability', array( $this, 'get_default_server_capability' ) );

        McpAdapter::instance();

        add_action( 'mcp_adapter_init', array( $this, 'create_server' ) );
    }

    public function create_server( McpAdapter $adapter ): void {
        $tools     = $this->get_server_tools();
        $resources = AbilityCatalog::get_names_by_type( 'resource' );

        try {
            $adapter->create_server(
                'hostinger-ai-assistant-mcp-server',
                HOSTINGER_AI_ASSISTANT_REST_API_BASE,
                'mcp',
                'Hostinger AI Assistant MCP Server',
                __( 'Custom MCP Server for Hostinger AI Assistant.', 'hostinger-ai-assistant' ),
                self::MCP_SERVER_VERSION,
                array( HttpTransport::class ),
                ErrorLogMcpErrorHandler::class,
                NullMcpObservabilityHandler::class,
                $tools,
                $resources,
                array(),
                array( $this, 'authenticate_request' ),
            );
        } catch ( Throwable $e ) {
            Functions::log_event( 'MCP server init failed: ' . $e->getMessage() );
        }
    }


    public function authenticate_request( WP_REST_Request $request ): WP_Error|bool {
        $api_key = $this->jwt_auth->get_token_from_request( $request );
        if ( empty( $api_key ) ) {
            return current_user_can( 'manage_options' );
        }

        if ( $this->jwt_auth->validate_jwt_token( $api_key ) !== true ) {
            return new WP_Error(
                'invalid_api_key',
                __( 'Invalid API key', 'hostinger-ai-assistant' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    public function get_default_server_capability(): string {
        return self::DEFAULT_SERVER_CAPABILITY;
    }

    private function get_server_tools(): array {
        /**
         * Filters whether the MCP server exposes the three discovery meta-tools instead of
         * registering every ability as a standalone MCP tool.
         *
         * Discovery keeps the tools/list response at three schemas no matter how many abilities
         * exist, at the cost of a discover-then-execute round trip. Return false to fall back to
         * advertising every ability as its own MCP tool.
         *
         * @param bool $use_discovery Whether to expose the discovery meta-tools. Default true.
         */
        if ( apply_filters( 'hostinger_ai_assistant_mcp_use_discovery', true ) ) {
            return array(
                DiscoverAbilitiesTool::ABILITY_NAME,
                GetAbilityInfoTool::ABILITY_NAME,
                ExecuteAbilityTool::ABILITY_NAME,
            );
        }

        return AbilityCatalog::get_names_by_type( 'tool' );
    }
}
