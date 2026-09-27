<?php

namespace Hostinger\EasyOnboarding\Rest;

use Hostinger\WpHelper\Utils as Helper;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use WP_REST_Request;
use WP_REST_Response;
use WP_Http;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class OnboardingRoutes {
    private $helper;
    private $config;
    private $proxy_client;

    public function __construct( Client $client, Helper $helper ) {
        $this->helper       = $helper;
        $this->config       = new Config();
        $this->proxy_client = new Client(
            $this->config->getConfigValue( 'base_proxy_rest_uri', HOSTINGER_EASY_ONBOARDING_PROXY_URI ),
            array(
                Config::TOKEN_HEADER  => $this->helper->getApiToken(),
                Config::DOMAIN_HEADER => $this->helper->getHostInfo(),
            )
        );
    }

    public function get_suggested_plugins( WP_REST_Request $request ): WP_REST_Response {
        $software_id = $this->get_software_id_from_request( $request );

        if ( empty( $software_id ) ) {
            return $this->create_error_response( 'Software ID is required' );
        }

        $endpoint = "/api/v1/installations/{$software_id}/plugins/suggested";

        return $this->make_api_request( $endpoint );
    }

    public function get_available_plugins( WP_REST_Request $request ): WP_REST_Response {
        $software_id = $this->get_software_id_from_request( $request );

        if ( empty( $software_id ) ) {
            return $this->create_error_response( 'Software ID is required' );
        }

        $parameters = $request->get_params();
        $search     = ! empty( $parameters['search'] ) ? sanitize_text_field( $parameters['search'] ) : '';

        if ( empty( $search ) ) {
            return $this->create_error_response( 'Search term is required' );
        }

        $endpoint = "/api/v1/installations/{$software_id}/plugins";
        $params   = array(
            'search' => $search,
        );

        return $this->make_api_request( $endpoint, $params );
    }

    public function install_plugins( WP_REST_Request $request ): WP_REST_Response {
        $software_id = $this->get_software_id_from_request( $request, true );

        if ( empty( $software_id ) ) {
            return $this->create_error_response( 'Software ID is required' );
        }

        $parameters = $request->get_json_params();
        $plugins    = ! empty( $parameters['plugins'] ) && is_array( $parameters['plugins'] ) ? $parameters['plugins'] : array();

        if ( empty( $plugins ) ) {
            return $this->create_error_response( 'Plugins array is required' );
        }

        $endpoint = "/api/v1/installations/{$software_id}/plugins/install";
        $params   = array(
            'plugins' => array_values( $plugins ),
        );

        return $this->make_api_post_request( $endpoint, $params );
    }

    public function get_suggested_themes( WP_REST_Request $request ): WP_REST_Response {
        $software_id = $this->get_software_id_from_request( $request );

        if ( empty( $software_id ) ) {
            return $this->create_error_response( 'Software ID is required' );
        }

        $endpoint = "/api/v1/installations/{$software_id}/themes";
        return $this->make_api_request( $endpoint );
    }

    public function get_astra_templates( WP_REST_Request $request ): WP_REST_Response {
        $software_id = $this->get_software_id_from_request( $request );

        if ( empty( $software_id ) ) {
            return $this->create_error_response( 'Software ID is required' );
        }

        $endpoint = "/api/v1/installations/{$software_id}/astra/templates";

        return $this->make_api_request( $endpoint );
    }

    public function get_website_data( WP_REST_Request $request ): WP_REST_Response {
        $domain    = $this->get_domain_from_request( $request );
        $site_path = parse_url( get_site_url(), PHP_URL_PATH );
        $directory = trim( $site_path ? $site_path : '', '/' );

        $params = array(
            'domain' => $domain,
        );

        $params['directory'] = $directory;

        return $this->make_api_request( '/api/v1/installations', $params );
    }

    public function get_astra_template_import_status( WP_REST_Request $request ): WP_REST_Response {
        $software_id = $this->get_software_id_from_request( $request );

        if ( empty( $software_id ) ) {
            return $this->create_error_response( 'Software ID is required' );
        }

        $endpoint = "/api/v1/installations/{$software_id}/astra/templates/import/status";

        return $this->make_api_request( $endpoint );
    }

    public function import_astra_template( WP_REST_Request $request ): WP_REST_Response {
        $software_id = $this->get_software_id_from_request( $request, true );

        if ( empty( $software_id ) ) {
            return $this->create_error_response( 'Software ID is required' );
        }

        $parameters  = $request->get_json_params();
        $template_id = ! empty( $parameters['template_id'] ) ? absint( $parameters['template_id'] ) : 0;

        $endpoint = "/api/v1/installations/{$software_id}/astra/templates/import";
        $params   = array(
            'templateId' => $template_id,
        );

        return $this->make_api_post_request( $endpoint, $params );
    }

    public function get_email_providers( WP_REST_Request $request ): WP_REST_Response {
        $software_id = $this->get_software_id_from_request( $request );

        if ( empty( $software_id ) ) {
            return $this->create_error_response( 'Software ID is required' );
        }

        if ( method_exists( $this->helper, 'getSiteUrlFromDb' ) ) {
            $site_url = $this->helper->getSiteUrlFromDb();
        } else {
            $site_url = (string) get_option( 'siteurl' );
        }

        $domain = preg_replace( '#^https?://#', '', $site_url );

        $endpoint = "/api/v1/installations/{$software_id}/emails/providers";
        $params   = array(
            'search' => $domain,
        );

        return $this->make_api_request( $endpoint, $params );
    }

    public function save_onboarding_options( WP_REST_Request $request ): WP_REST_Response {
        $parameters = $request->get_json_params();

        if ( empty( $parameters ) ) {
            return $this->create_error_response( 'No options provided' );
        }

        try {
            $saved_options = $this->process_and_save_options( $parameters );

            $response = new WP_REST_Response();
            $response->set_status( \WP_Http::OK );
            $response->set_data(
                array(
                    'status' => 'success',
                    'data'   => $saved_options,
                )
            );

            return $response;
        } catch ( \Exception $exception ) {
            $this->helper->errorLog( 'Hostinger Easy Onboarding: Error saving onboarding options: ' . $exception->getMessage() );

            return $this->create_error_response( $exception->getMessage() );
        }
    }

    private function get_domain_from_request( WP_REST_Request $request ): string {
        $parameters = $request->get_params();
        $domain     = ! empty( $parameters['domain'] ) ? sanitize_text_field( $parameters['domain'] ) : '';

        if ( empty( $domain ) ) {
            $siteurl = get_option( 'siteurl', $this->helper->getHostInfo() );
            $domain  = parse_url( $siteurl, PHP_URL_HOST );
        }

        return $domain;
    }

    private function get_software_id_from_request( WP_REST_Request $request, bool $use_json_params = false ): string {
        $parameters  = $use_json_params ? $request->get_json_params() : $request->get_params();
        $software_id = ! empty( $parameters['software_id'] ) ? sanitize_text_field( $parameters['software_id'] ) : '';

        return $software_id;
    }

    private function create_error_response( string $message ): WP_REST_Response {
        $response = new WP_REST_Response();
        $response->set_status( \WP_Http::BAD_REQUEST );
        $response->set_data(
            array(
                'status'  => 'error',
                'message' => $message,
            )
        );

        return $response;
    }

    private function make_api_request( string $endpoint, array $params = array(), string $error_prefix = 'Hostinger Easy Onboarding' ): WP_REST_Response {
        $response = new WP_REST_Response();
        $response->set_status( \WP_Http::OK );

        try {
            $request = $this->proxy_client->get( $endpoint, $params );
            $data    = $this->process_api_response( $request, $error_prefix );

            if ( $data['status'] === 'error' ) {
                $response->set_status( \WP_Http::BAD_REQUEST );
            }
        } catch ( \Exception $exception ) {
            $response->set_status( \WP_Http::BAD_REQUEST );
            $this->helper->errorLog( "$error_prefix: Error sending request: " . $exception->getMessage() );
            $data = array(
                'status'  => 'error',
                'message' => $exception->getMessage(),
            );
        }

        $response->set_data( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );

        return $response;
    }

    private function make_api_post_request( string $endpoint, array $params = array(), string $error_prefix = 'Hostinger Easy Onboarding' ): WP_REST_Response {
        $response = new WP_REST_Response();
        $response->set_status( \WP_Http::OK );

        try {
            $request = $this->proxy_client->post( $endpoint, $params );
            $data    = $this->process_api_response( $request, $error_prefix );

            if ( $data['status'] === 'error' ) {
                $response->set_status( \WP_Http::BAD_REQUEST );
            }
        } catch ( \Exception $exception ) {
            $response->set_status( \WP_Http::BAD_REQUEST );
            $this->helper->errorLog( "$error_prefix: Error sending request: " . $exception->getMessage() );
            $data = array(
                'status'  => 'error',
                'message' => $exception->getMessage(),
            );
        }

        $response->set_data( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );

        return $response;
    }

    private function process_api_response( $request, string $error_prefix ): array {
        if ( is_wp_error( $request ) ) {
            return $this->handle_wp_error( $request, $error_prefix );
        }

        $response_code = (int) wp_remote_retrieve_response_code( $request );

        if ( $response_code >= 200 && $response_code < 300 ) {
            return $this->handle_success_response( $request );
        }

        return $this->handle_http_error( $request, $response_code, $error_prefix );
    }

    private function handle_wp_error( \WP_Error $error, string $error_prefix ): array {
        $message = $error->get_error_message();
        $this->helper->errorLog( "$error_prefix: WP Error: $message" );

        return array(
            'status'  => 'error',
            'message' => $message,
        );
    }

    private function handle_success_response( array $request ): array {
        $body = wp_remote_retrieve_body( $request );

        if ( empty( $body ) ) {
            return array(
                'status' => 'success',
                'data'   => array(),
            );
        }

        return $this->parse_json_response( $body );
    }

    private function parse_json_response( string $body ): array {
        $json = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return array(
                'status'  => 'error',
                'message' => 'Invalid JSON response: ' . json_last_error_msg(),
            );
        }

        return array(
            'status' => 'success',
            'data'   => isset( $json['data'] ) ? $json['data'] : $json,
        );
    }

    private function handle_http_error( array $request, int $response_code, string $error_prefix ): array {
        $body = wp_remote_retrieve_body( $request );
        $this->helper->errorLog( "$error_prefix: HTTP Error: Response code $response_code. Body: $body" );

        return array(
            'status'  => 'error',
            'message' => "HTTP Error: Response code $response_code" . ( ! empty( $body ) ? " - $body" : '' ),
        );
    }

    private function process_and_save_options( array $parameters ): array {
        $saved_options = array();

        if ( isset( $parameters['website_type'] ) ) {
            $website_type = sanitize_text_field( $parameters['website_type'] );
            $this->validate_website_type( $website_type );
            update_option( 'hostinger_website_type', $website_type );
            $saved_options['website_type'] = $website_type;
        }

        if ( isset( $parameters['hostinger_template_id'] ) ) {
            $template_id = absint( $parameters['hostinger_template_id'] );
            update_option( 'hostinger_template_id', $template_id );
            $saved_options['hostinger_template_id'] = $template_id;
        }

        if ( isset( $parameters['hostinger_theme'] ) ) {
            $theme = sanitize_text_field( $parameters['hostinger_theme'] );
            update_option( 'hostinger_theme', $theme );
            $saved_options['hostinger_theme'] = $theme;
        }

        if ( isset( $parameters['hostinger_plugins'] ) && is_array( $parameters['hostinger_plugins'] ) ) {
            $plugins = array_map( 'sanitize_text_field', $parameters['hostinger_plugins'] );
            update_option( 'hostinger_plugins', $plugins );
            $saved_options['hostinger_plugins'] = $plugins;
        }

        if ( isset( $parameters['hostinger_woocommerce_onboarding'] ) ) {
            $woo_flag = (int) $parameters['hostinger_woocommerce_onboarding'];
            update_option( 'hostinger_woocommerce_onboarding', $woo_flag );
            $saved_options['hostinger_woocommerce_onboarding'] = $woo_flag;
        }

        if ( isset( $parameters['hostinger_ai_content_generated'] ) ) {
            $ai_content_flag = (int) $parameters['hostinger_ai_content_generated'];
            update_option( 'hostinger_ai_content_generated', $ai_content_flag );
            $saved_options['hostinger_ai_content_generated'] = $ai_content_flag;
        }

        if ( isset( $parameters['hostinger_ai_builder'] ) ) {
            $ai_builder_flag = (int) $parameters['hostinger_ai_builder'];
            update_option( 'hostinger_ai_builder', $ai_builder_flag );
            $saved_options['hostinger_ai_builder'] = $ai_builder_flag;
        }

        if ( isset( $parameters['hostinger_onboarding_completed'] ) ) {
            $onboarding_completed = (int) $parameters['hostinger_onboarding_completed'];
            update_option( 'hostinger_onboarding_completed', $onboarding_completed );
            $saved_options['hostinger_ai_builder'] = $onboarding_completed;
        }

        return $saved_options;
    }

    private function validate_website_type( string $website_type ): void {
        $allowed_types = array(
            'online store',
            'blog',
            'business',
            'portfolio',
            'affiliate-marketing',
            'landing page',
            'booking',
            'other',
        );

        if ( ! in_array( $website_type, $allowed_types, true ) ) {
            throw new \Exception( "Invalid website type: $website_type. Allowed types: " . implode( ', ', $allowed_types ) );
        }
    }

    public function get_homepage_edit_url( WP_REST_Request $request ): WP_REST_Response {
        $response = new WP_REST_Response();
        $response->set_status( WP_Http::OK );

        $front_page_id   = get_option( 'page_on_front' );
        $show_on_front   = get_option( 'show_on_front' );
        $has_static_page = $show_on_front === 'page' && $front_page_id;

        if ( $has_static_page ) {
            $easy_onboarding_helper = new \Hostinger\EasyOnboarding\Helper();

            if ( $easy_onboarding_helper->is_page_built_with_elementor( (int) $front_page_id ) ) {
                $edit_url = $easy_onboarding_helper->get_elementor_edit_url( (int) $front_page_id );
            } else {
                $query_args = array(
                    'post'   => $front_page_id,
                    'action' => 'edit',
                );

                $edit_url = add_query_arg( $query_args, admin_url( 'post.php' ) );
            }

            $response->set_data(
                array(
                    'status' => 'success',
                    'data'   => array(
                        'url' => $edit_url,
                    ),
                )
            );

            return $response;
        }

        if ( wp_is_block_theme() ) {
            $response->set_data(
                array(
                    'status' => 'success',
                    'data'   => array(
                        'url' => admin_url( 'site-editor.php' ),
                    ),
                )
            );

            return $response;
        }

        $response->set_data(
            array(
                'status' => 'success',
                'data'   => array(
                    'url' => admin_url( 'site-editor.php' ),
                ),
            )
        );

        return $response;
    }
}
