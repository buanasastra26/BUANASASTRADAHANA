<?php

namespace Hostinger\Reach\Integrations\OptInMonster;

use Exception;
use Hostinger\Reach\Dto\PluginData;
use Hostinger\Reach\Dto\ReachContact;
use Hostinger\Reach\Functions;
use Hostinger\Reach\Integrations\Integration;
use Hostinger\Reach\Integrations\IntegrationInterface;
use OMAPI;
use OMAPI_Api;
use stdClass;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

if ( ! DEFINED( 'ABSPATH' ) ) {
    exit;
}

class OptInMonsterIntegration extends Integration implements IntegrationInterface {

    public const INTEGRATION_NAME = 'optinmonster';
    protected Functions $functions;

    public function __construct( Functions $functions ) {
        $this->functions = $functions;
    }

    public function active_integration_hooks(): void {
        $this->enqueue_assets();
        $this->register_routes();
    }

    public function get_post_type(): array {
        return array( 'omapi' );
    }

    public static function get_name(): string {
        return self::INTEGRATION_NAME;
    }

    public function get_plugin_data(): PluginData {
        return PluginData::from_array(
            array(
                'id'             => self::INTEGRATION_NAME,
                'title'          => __( 'OptinMonster', 'hostinger-reach' ),
                'folder'         => 'optinmonster',
                'file'           => 'optin-monster-wp-api.php',
                'admin_url'      => 'admin.php?page=optin-monster-dashboard',
                'add_form_url'   => 'admin.php?page=optin-monster-templates&type=popup',
                'edit_url'       => 'admin.php?page=optin-monster-campaigns&campaignId={post_name}',
                'url'            => 'https://optinmonster.com/',
                'download_url'   => 'https://downloads.wordpress.org/plugin/optinmonster.zip',
                'icon'           => 'https://ps.w.org/optinmonster/assets/icon-256x256.png',
                'import_enabled' => true,
            )
        );
    }

    public function submit_optinmonster( WP_REST_Request $request ): WP_REST_Response {
        if ( ! $this->is_authorized( $request ) ) {
            return $this->handle_response( 403, array( 'errors' => 'You cannot perform this action' ) );
        }

        try {
            $data    = json_decode( $request->get_body() ?? '', true );
            $form_id = $this->get_post_id_by_form_id( $data['formId'] ?? '' );
            if ( $form_id && ! $this->is_form_enabled( $form_id ) ) {
                return $this->handle_response( 403, array( 'errors' => 'Form is not active' ) );
            }
            $email = $this->find_email( $data['fields'] ?? array() );
            if ( $email ) {
                $campaign_name = $data['tags']['campaign_name'] ?? '';
                $form_name     = $data['tags']['form_name'] ?? '';
                $group         = $campaign_name . ' - ' . $form_name;
                do_action(
                    'hostinger_reach_submit',
                    array(
                        'email'    => $email,
                        'group'    => empty( $group ) ? self::INTEGRATION_NAME : $group,
                        'metadata' => array(
                            'plugin' => self::INTEGRATION_NAME,
                        ),
                    )
                );
            }
        } catch ( Exception $e ) {
            return $this->handle_response( 400, array( 'errors' => 'Invalid JSON data' ) );
        }

        return $this->handle_response( 200, array( 'message' => 'success' ) );
    }

    public function get_import_summary(): array {
        $leads     = $this->do_request( 'v2', 'leads' );
        $campaigns = $leads?->campaigns ?? array();
        $summary   = array();
        foreach ( $campaigns as $campaign ) {
            $form_id                                = $this->get_post_id_by_form_id( $campaign->slug );
            $summary[ $form_id ?: $campaign->slug ] = array(
                'title'    => $campaign->name,
                'contacts' => $campaign->leads_count_all_time,
            );
        }

        return $summary;
    }

    public function get_contacts( mixed $form_id = null, ?int $limit = 100, ?int $offset = 0 ): array {
        $campaign_slug = $form_id;
        $post          = get_post( $form_id );
        if ( $post ) {
            $campaign_slug = $post->post_name;
        }

        $campaign_id = $this->get_campaign_by_slug( $campaign_slug );
        $page        = intdiv( $offset, $limit ) + 1;

        $leads = $this->do_request(
            'v2',
            'leads',
            array(
                'campaigns[]' => $campaign_id,
                'perPage'     => $limit,
                'page'        => $page,
            )
        );

        if ( ! $leads?->pagination || $this->is_overflowing_pagination( $leads->pagination, $page ) ) {
            return array();
        }

        $entries = array_map(
            function ( mixed $lead ) use ( $form_id ) {
                $contact = new ReachContact(
                    $lead->email,
                    $lead?->fist_name ?? '',
                    $lead?->last_name ?? '',
                    array(
                        'plugin'  => self::INTEGRATION_NAME,
                        'form_id' => $form_id,
                    )
                );

                return $contact->to_array();
            },
            $leads->leads ?? array()
        );

        return $entries;
    }

    /**
     * OptinMonster API returns the last page when the currentPage overflows the number of items.
     * So we need to check that edge case manually here.
     */
    private function is_overflowing_pagination( stdClass $pagination, int $current_page ): bool {
        // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
        return $current_page !== $pagination->currentPage ?? 0;
    }

    private function get_campaign_by_slug( string $campaign_slug ): string {
        $leads     = $this->do_request( 'v2', 'leads' );
        $campaigns = $leads?->campaigns ?? array();

        $campaign = null;
        foreach ( $campaigns as $item ) {
            $slug = $item->slug ?? '';
            if ( $slug === $campaign_slug ) {
                $campaign = $item;
                break;
            }
        }

        if ( $campaign ) {
            return $campaign->id ?? '';
        }

        return '';
    }

    private function do_request( string $version, string $route, array $args = array() ): mixed {
        if ( ! $this->is_optinmonster_api_available() ) {
            return null;
        }
        $api     = OMAPI_Api::build( $version, $route, 'GET', OMAPI::get_instance()->get_api_credentials() );
        $request = $api->request( $args );
        if ( is_wp_error( $request ) || empty( $request ) ) {
            return null;
        }

        return $request;
    }

    private function is_optinmonster_api_available(): bool {
        return class_exists( '\OMAPI_Api' ) && class_exists( '\OMAPI' );
    }

    private function is_authorized( WP_REST_Request $request ): bool {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return false;
        }

        return true;
    }

    private function register_routes(): void {
        add_action(
            'rest_api_init',
            function (): void {
                register_rest_route(
                    'hostinger-reach/v1',
                    '/optinmonster',
                    array(
                        'methods'             => 'POST',
                        'callback'            => array( $this, 'submit_optinmonster' ),
                        'permission_callback' => '__return_true',
                    )
                );
            }
        );
    }

    private function enqueue_assets(): void {
        $handler_name = 'hostinger_reach_' . self::INTEGRATION_NAME;
        $js_file      = $this->functions->get_frontend_dir() . self::INTEGRATION_NAME . '.js';

        if ( $js_file ) {
            wp_enqueue_script(
                $handler_name,
                Functions::get_frontend_url() . self::INTEGRATION_NAME . '.js',
                array(),
                filemtime( $this->functions->get_frontend_dir() . self::INTEGRATION_NAME . '.js' ),
                true
            );

            wp_localize_script(
                $handler_name,
                $handler_name . '_data',
                array(
                    'nonce'   => wp_create_nonce( 'wp_rest' ),
                    'restUrl' => esc_url_raw( rest_url( 'hostinger-reach/v1/optinmonster' ) ),
                )
            );
        }
    }

    private function find_email( array $data ): string {
        foreach ( $data as $value ) {
            $sanitized_value = sanitize_email( $value );
            if ( filter_var( $sanitized_value, FILTER_VALIDATE_EMAIL ) ) {
                return $value;
            }
        }

        return '';
    }

    private function get_post_id_by_form_id( string $form_id ): int {
        if ( ! $form_id ) {
            return 0;
        }
        $post = get_page_by_path( $form_id, OBJECT, 'omapi' );
        if ( $post instanceof WP_Post ) {
            return $post->ID;
        }

        return 0;
    }

    private function handle_response( int $code, array $data ): WP_REST_Response {
        $response = new WP_REST_Response();
        $response->set_status( $code );
        $response->set_data( $data );

        return $response;
    }
}
