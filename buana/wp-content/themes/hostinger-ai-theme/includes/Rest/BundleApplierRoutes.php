<?php

namespace Hostinger\AiTheme\Rest;

use Hostinger\AiTheme\Builder\SiteApplier;
use InvalidArgumentException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * REST entry point for applying a `POST /wordpress/generate-website` envelope.
 *
 * Self-registers under the existing `hostinger-ai-plugin/v1` namespace so it
 * sits beside the legacy builder routes without touching their wiring. The
 * theme is a pure applier: this receives the already-generated envelope in the
 * request body, applies it, and returns a summary.
 */
class BundleApplierRoutes {

    private SiteApplier $applier;

    public function __construct( SiteApplier $applier ) {
        $this->applier = $applier;
    }

    public function init(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'apply-site',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'apply_site' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function apply_site( WP_REST_Request $request ) {
        $envelope = $request->get_json_params();

        if ( ! is_array( $envelope ) || empty( $envelope ) ) {
            return new WP_Error(
                'invalid_envelope',
                'Request body must be a website envelope JSON object.',
                array( 'status' => 400 )
            );
        }

        try {
            $result = $this->applier->apply( $envelope );
        } catch ( InvalidArgumentException $e ) {
            return new WP_Error( 'unsupported_envelope', $e->getMessage(), array( 'status' => 422 ) );
        } catch ( Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SiteApplier failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            return new WP_Error( 'apply_failed', 'Failed to apply website envelope.', array( 'status' => 500 ) );
        }

        return new WP_REST_Response(
            array(
                'applied' => true,
                'result'  => $result,
            ),
            200
        );
    }

    public function permission_check(): bool {
        return is_user_logged_in() && current_user_can( 'manage_options' );
    }
}
