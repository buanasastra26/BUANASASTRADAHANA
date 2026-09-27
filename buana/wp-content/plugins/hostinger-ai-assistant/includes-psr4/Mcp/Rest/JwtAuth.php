<?php

namespace Hostinger\AiAssistant\Mcp\Rest;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Hostinger\AiAssistant\Functions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class JwtAuth {
    public const JWT_ACCESS_EXP_MIN     = HOUR_IN_SECONDS;
    public const JWT_ACCESS_EXP_MAX     = MONTH_IN_SECONDS;
    public const JWT_ACCESS_EXP_DEFAULT = HOUR_IN_SECONDS;
    public const TOKEN_HEADER           = 'X-Hostinger-JWT-Token';
    private const TOKEN_REGISTRY_OPTION = 'hostinger_ai_assistant_mcp_jwt_token_registry';
    private const BEARER_PREFIX         = 'bearer ';

    public function init(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route(
            HOSTINGER_AI_ASSISTANT_REST_API_BASE,
            '/jwt/token',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'generate_jwt_token' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'expires_in' => array(
                        'type'        => 'integer',
                        'description' => __( 'Token expiration time in seconds (3600-86400)', 'hostinger-ai-assistant' ),
                        'required'    => false,
                        'minimum'     => self::JWT_ACCESS_EXP_MIN,
                        'maximum'     => self::JWT_ACCESS_EXP_MAX,
                        'default'     => self::JWT_ACCESS_EXP_DEFAULT,
                    ),
                ),
            )
        );

        register_rest_route(
            HOSTINGER_AI_ASSISTANT_REST_API_BASE,
            '/jwt/revoke',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'revoke_token' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );
    }

    public function check_permissions(): bool {
        return current_user_can( 'manage_options' );
    }

    public function generate_jwt_token( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $params     = $request->get_json_params();
        $expires_in = isset( $params['expires_in'] ) ? intval( $params['expires_in'] ) : self::JWT_ACCESS_EXP_DEFAULT;

        if ( $expires_in < self::JWT_ACCESS_EXP_MIN || $expires_in > self::JWT_ACCESS_EXP_MAX ) {
            Functions::log_event( 'Invalid token expiration requested: ' . $expires_in );

            return new WP_Error( 'invalid_expiration', sprintf( /* translators: 1: minimum expiration time in seconds, 2: maximum expiration time in seconds */ __( 'Token expiration must be between %1$d seconds (1 hour) and %2$d seconds (1 day)', 'hostinger-ai-assistant' ), self::JWT_ACCESS_EXP_MIN, self::JWT_ACCESS_EXP_MAX ), array( 'status' => 400 ) );
        }

        $user_id = get_current_user_id();

        return rest_ensure_response( $this->create_token( $user_id, $expires_in ) );
    }

    public function revoke_token( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $params = $request->get_json_params();
        $jti    = isset( $params['jti'] ) ? sanitize_text_field( $params['jti'] ) : '';

        if ( empty( $jti ) ) {
            Functions::log_event( 'Token revocation failed: missing token ID' );

            return new WP_Error( 'missing_jti', __( 'Token ID is required.', 'hostinger-ai-assistant' ), array( 'status' => 400 ) );
        }

        $registry = get_option( self::TOKEN_REGISTRY_OPTION, array() );

        if ( ! isset( $registry[ $jti ] ) ) {
            Functions::log_event( 'Token revocation failed: token not found - ' . $jti );

            return new WP_Error( 'token_not_found', __( 'Token not found in registry.', 'hostinger-ai-assistant' ), array( 'status' => 404 ) );
        }

        unset( $registry[ $jti ] );
        update_option( self::TOKEN_REGISTRY_OPTION, $registry );

        Functions::log_event( 'Token revoked successfully: ' . $jti );

        return rest_ensure_response(
            array(
                'message' => __( 'Token revoked successfully.', 'hostinger-ai-assistant' ),
            )
        );
    }

    public function validate_jwt_token( string $token ): bool|WP_Error {
        try {
            $decoded = JWT::decode( $token, new Key( $this->get_jwt_secret_key(), 'HS256' ) );

            if ( ! isset( $decoded->jti ) || ! $this->is_token_valid( $decoded->jti ) ) {
                $jti = isset( $decoded->jti ) ? $decoded->jti : 'unknown';
                Functions::log_event( 'Token validation failed: invalid or expired token - ' . $jti );

                return new WP_Error( 'token_invalid', __( 'Token is invalid, expired, or has been revoked.', 'hostinger-ai-assistant' ), array( 'status' => 401 ) );
            }

            if ( ! isset( $decoded->user_id ) ) {
                Functions::log_event( 'Token validation failed: missing user_id in token' );

                return new WP_Error( 'token_malformed', __( 'Token is malformed: missing user_id.', 'hostinger-ai-assistant' ), array( 'status' => 403 ) );
            }

            $user = get_user_by( 'id', $decoded->user_id );
            if ( ! $user ) {
                Functions::log_event( 'Token validation failed: user not found - ID: ' . $decoded->user_id );

                return new WP_Error( 'token_invalid', __( 'User associated with token no longer exists.', 'hostinger-ai-assistant' ), array( 'status' => 403 ) );
            }

            wp_set_current_user( $user->ID );
            Functions::log_event( 'Token validated successfully for user ID: ' . $user->ID );

            return true;
        } catch ( Exception $e ) {
            Functions::log_event( 'Token validation exception: ' . $e->getMessage() );

            return new WP_Error( 'token_invalid', sprintf( /* translators: %s: error message from JWT library */ __( 'Token validation failed: %s', 'hostinger-ai-assistant' ), $e->getMessage() ), array( 'status' => 403 ) );
        }
    }

    public function get_token_from_request( WP_REST_Request $request ): string {
        $token = trim( (string) $request->get_header( self::TOKEN_HEADER ) );

        if ( ! empty( $token ) ) {
            return $token;
        }

        $authorization = trim( (string) $request->get_header( 'authorization' ) );

        if ( stripos( $authorization, self::BEARER_PREFIX ) !== 0 ) {
            return '';
        }

        return trim( substr( $authorization, strlen( self::BEARER_PREFIX ) ) );
    }

    protected function get_raw_secret_key(): string {
        return defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
    }

    protected function get_jwt_secret_key(): string {
        $key = $this->get_raw_secret_key();
        if ( strlen( $key ) < 32 ) {
            $key = hash( 'sha256', wp_salt( 'secure_auth' ) );
        }

        return $key;
    }

    public function create_token( int $user_id, int $expires_in = self::JWT_ACCESS_EXP_DEFAULT ): array {
        $issued_at  = time();
        $expires_at = $issued_at + $expires_in;
        $jti        = wp_generate_password( 32, false );

        $payload = array(
            'iss'     => get_bloginfo( 'url' ),
            'iat'     => $issued_at,
            'exp'     => $expires_at,
            'user_id' => $user_id,
            'jti'     => $jti,
        );

        $token = JWT::encode( $payload, $this->get_jwt_secret_key(), 'HS256' );

        $this->register_token( $jti, $user_id, $issued_at, $expires_at );

        Functions::log_event( 'JWT token generated for user ID: ' . $user_id );

        return array(
            'token'      => $token,
            'user_id'    => $user_id,
            'expires_in' => $expires_in,
            'expires_at' => $expires_at,
        );
    }

    private function register_token( string $jti, int $user_id, int $issued_at, int $expires_at ): void {
        $registry = get_option( self::TOKEN_REGISTRY_OPTION, array() );

        $registry[ $jti ] = array(
            'user_id'    => $user_id,
            'issued_at'  => $issued_at,
            'expires_at' => $expires_at,
        );

        update_option( self::TOKEN_REGISTRY_OPTION, $registry );
    }

    private function is_token_valid( string $jti ): bool {
        $registry = get_option( self::TOKEN_REGISTRY_OPTION, array() );

        if ( ! isset( $registry[ $jti ] ) ) {
            return false;
        }

        $token_data = $registry[ $jti ];

        if ( time() > $token_data['expires_at'] ) {
            return false;
        }

        return true;
    }
}
