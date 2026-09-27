<?php

namespace Hostinger\AiAssistant\Cli;

use Hostinger\AiAssistant\Mcp\Rest\JwtAuth;
use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Generates JWT tokens for authenticating against the Hostinger AI MCP endpoint.
 */
class JwtCommand {
    private JwtAuth $jwt_auth;

    public function __construct( JwtAuth $jwt_auth ) {
        $this->jwt_auth = $jwt_auth;
    }

    /**
     * Generates a JWT for authenticating against the Hostinger AI MCP endpoint.
     *
     * ## OPTIONS
     *
     * [--expires-in=<seconds>]
     * : Token lifetime in seconds. Must be between 3600 (1 hour) and 86400 (1 day).
     * ---
     * default: 3600
     * ---
     *
     * [--format=<format>]
     * : Render output in a structured format. When omitted, only the raw token is printed.
     * ---
     * options:
     *   - json
     *   - yaml
     *   - table
     * ---
     *
     * ## EXAMPLES
     *
     *     # Print a token for the lowest-ID administrator.
     *     $ wp hostinger-ai jwt
     *
     *     # Print a one-day token for a specific user as JSON.
     *     $ wp hostinger-ai jwt --user=admin --expires-in=86400 --format=json
     *
     * @when after_wp_load
     *
     * @param array $args       Positional arguments (unused).
     * @param array $assoc_args Associative arguments.
     */
    public function __invoke( array $args, array $assoc_args ): void {
        try {
            $user_id    = $this->resolve_user_id();
            $expires_in = $this->resolve_expires_in( $assoc_args );
            $result     = $this->build_result( $user_id, $expires_in );
        } catch ( InvalidArgumentException $e ) {
            // WP_CLI::error() halts execution; the return guards against fall-through
            // (and an undefined $result) should it ever be configured not to exit.
            \WP_CLI::error( $e->getMessage() );

            return;
        }

        $format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : '';

        if ( $format === '' ) {
            \WP_CLI::line( $result['token'] );

            return;
        }

        \WP_CLI\Utils\format_items( $format, array( $result ), array_keys( $result ) );
    }

    /**
     * Resolves the WordPress user the token will be issued for.
     *
     * Uses the current WP-CLI user (set via the global `--user` flag) and falls
     * back to the lowest-ID administrator. The resolved user must have
     * `manage_options`. Public for direct testing without the WP-CLI runtime.
     *
     * @throws InvalidArgumentException When no capable user can be resolved.
     */
    public function resolve_user_id(): int {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            $user_id = $this->get_default_admin_id();
        }

        if ( ! $user_id ) {
            throw new InvalidArgumentException(
                __( 'No administrator found to issue a token for. Pass --user=<id|login|email>.', 'hostinger-ai-assistant' )
            );
        }

        if ( ! user_can( $user_id, 'manage_options' ) ) {
            throw new InvalidArgumentException(
                sprintf(
                    /* translators: %d: WordPress user ID */
                    __( 'User %d lacks the manage_options capability required to issue a token.', 'hostinger-ai-assistant' ),
                    $user_id
                )
            );
        }

        return $user_id;
    }

    /**
     * Resolves and validates the requested token lifetime in seconds.
     *
     * Public for direct testing without the WP-CLI runtime.
     *
     * @param array $assoc_args Associative CLI arguments.
     *
     * @throws InvalidArgumentException When the value is non-numeric or out of bounds.
     */
    public function resolve_expires_in( array $assoc_args ): int {
        if ( ! isset( $assoc_args['expires-in'] ) ) {
            return JwtAuth::JWT_ACCESS_EXP_DEFAULT;
        }

        if ( ! is_numeric( $assoc_args['expires-in'] ) ) {
            throw new InvalidArgumentException(
                __( 'Token expiration (--expires-in) must be an integer number of seconds.', 'hostinger-ai-assistant' )
            );
        }

        $expires_in = (int) $assoc_args['expires-in'];

        if ( $expires_in < JwtAuth::JWT_ACCESS_EXP_MIN || $expires_in > JwtAuth::JWT_ACCESS_EXP_MAX ) {
            throw new InvalidArgumentException(
                sprintf(
                    /* translators: 1: minimum expiration in seconds, 2: maximum expiration in seconds */
                    __( 'Token expiration must be between %1$d and %2$d seconds.', 'hostinger-ai-assistant' ),
                    JwtAuth::JWT_ACCESS_EXP_MIN,
                    JwtAuth::JWT_ACCESS_EXP_MAX
                )
            );
        }

        return $expires_in;
    }

    /**
     * Builds the command result: the token plus the MCP endpoint URL to use it against.
     *
     * Public for direct testing without the WP-CLI runtime.
     *
     * @param int $user_id    User the token is issued for.
     * @param int $expires_in Token lifetime in seconds.
     */
    public function build_result( int $user_id, int $expires_in ): array {
        $token_data = $this->jwt_auth->create_token( $user_id, $expires_in );

        return array(
            'token'      => $token_data['token'],
            'user_id'    => $token_data['user_id'],
            'expires_in' => $token_data['expires_in'],
            'expires_at' => $token_data['expires_at'],
            'mcp_url'    => rest_url( HOSTINGER_AI_ASSISTANT_REST_API_BASE . '/mcp' ),
        );
    }

    private function get_default_admin_id(): int {
        $admins = get_users(
            array(
                'role'    => 'administrator',
                'orderby' => 'ID',
                'order'   => 'ASC',
                'number'  => 1,
                'fields'  => 'ID',
            )
        );

        return ! empty( $admins ) ? (int) $admins[0] : 0;
    }
}
