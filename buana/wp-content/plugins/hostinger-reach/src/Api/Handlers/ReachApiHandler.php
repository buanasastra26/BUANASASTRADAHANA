<?php

namespace Hostinger\Reach\Api\Handlers;

use Hostinger\Reach\Api\ApiKeyManager;
use Hostinger\Reach\Api\ResourceIdManager;
use Hostinger\Reach\Functions;
use Hostinger\Reach\Integrations\Reach\ReachFormIntegration;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ReachApiHandler extends ApiHandler {
    public const CONNECTION_SUCCESS_TRANSIENT             = 'hostinger_reach_connection_success';
    private const CONNECTION_SUCCESS_TRANSIENT_EXPIRATION = MINUTE_IN_SECONDS;

    protected string $hostinger_auth_url;
    protected string $reach_domain;
    public ApiKeyManager $api_key_manager;
    public ResourceIdManager $resource_id_manager;

    public function __construct( Functions $functions, ApiKeyManager $api_key_manager, ResourceIdManager $resource_id_manager ) {
        parent::__construct( $functions );
        $this->api_key_manager     = $api_key_manager;
        $this->resource_id_manager = $resource_id_manager;
        $this->set_api_base_name();
        $this->init_hooks();
    }


    public function get_not_connected_error_message(): string {
        return __( 'Your site is not connected to Reach. Connect to Reach and try again.', 'hostinger-reach' );
    }

    public function init_hooks(): void {

        add_filter( 'hostinger_reach_is_connected', array( $this, 'is_connected' ) );

        add_filter(
            'allowed_http_origins',
            function ( $origins ) {
                $origins[] = HOSTINGER_REACH_REST_URI;

                return $origins;
            }
        );

        add_filter(
            'rest_exposed_cors_headers',
            function ( array $exposed_headers ): array {
                $exposed_headers[] = 'X-WP-Total';
                $exposed_headers[] = 'X-WP-TotalPages';
                $exposed_headers[] = 'Link';

                return array_values( array_unique( $exposed_headers ) );
            }
        );

        add_filter(
            'rest_allowed_cors_headers',
            function ( array $allowed_headers, WP_REST_Request $request ): array {
                $allowed_headers[] = 'Authorization';
                $allowed_headers[] = 'Content-Type';
                $allowed_headers[] = 'X-WP-Nonce';

                return array_values( array_unique( $allowed_headers ) );
            },
            10,
            2
        );

        /**
         * Submits a contact to Reach
         *
         * @param array $data The data to be sent
         * email: string - - Contact email
         * name: string (optional) - Contact name
         * surname: string (optional) - Contact surname
         * group: string (optional) - The group to which the contact belongs WordPress by default
         * metadata: array (optional) - Additional metadata to be sent with the contact
         *    - plugin: string - The name of the plugin sending the contact
         *
         * example of usage:
         *
         * do_action( 'hostinger_reach_submit', array(
         *     'email' => 'john.doe@example.com',
         *     'name' => 'John',
         *     'surname' => 'Doe',
         *     'group' => 'your plugin name',
         *     'metadata' => array(
         *         'plugin' => 'your plugin name',
         *     )
         * ))
         *
         * @fires hostinger_reach_contact_submitted when the contact is submitted successfully
         * @fires hostinger_reach_contact_failed when the contact submission fails
         * @since 1.1.0
         *
         */
        if ( ! has_action( 'hostinger_reach_submit' ) ) {
            add_action( 'hostinger_reach_submit', array( $this, 'post_contact' ) );
        }
    }

    public function get_default_headers(): array {
        return array(
            'Authorization' => 'Bearer ' . $this->api_key_manager->get_token(),
        );
    }

    public function is_connected(): bool {
        return ! empty( $this->api_key_manager->get_token() );
    }

    public function get_reach_domain(): string {
        return $this->reach_domain;
    }

    public function get_resource_id(): string {
        if ( ! $this->is_connected() ) {
            return ResourceIdManager::NON_EXISTENT_RESOURCE_ID;
        }

        $resource_id = $this->resource_id_manager->get_resource_id();

        if ( ! empty( $resource_id ) ) {
            return $resource_id;
        }

        return $this->generate_resource_id();
    }

    public function generate_resource_id(): string {
        $overview_data = $this->get_overview_handler()->get_data();
        if ( isset( $overview_data['data']['resourceId'] ) ) {
            $this->resource_id_manager->store_resource_id( $overview_data['data']['resourceId'] );
        } else {
            $this->resource_id_manager->store_resource_id( ResourceIdManager::NON_EXISTENT_RESOURCE_ID );
        }

        return $this->resource_id_manager->get_resource_id();
    }

    public function post_contact_handler( WP_REST_Request $request ): WP_REST_Response {
        if ( ! $this->is_authorized( $request ) ) {
            return $this->handle_wp_error( new WP_Error( $this->get_not_connected_error_message(), 'You cannot perform this action' ) );
        }

        $email    = $request->get_param( 'email' );
        $name     = $request->get_param( 'name' );
        $surname  = $request->get_param( 'surname' );
        $form_id  = $request->get_param( 'id' );
        $metadata = $request->get_param( 'metadata' );
        $tags     = $request->get_param( 'tags' );
        $group    = apply_filters( 'hostinger_reach_get_group', $request->get_param( 'group' ), $form_id );

        return $this->post_contact(
            array(
                'form_id'  => $form_id,
                'group'    => $group,
                'tags'     => $tags,
                'email'    => $email,
                'name'     => $name,
                'surname'  => $surname,
                'metadata' => $metadata,
            )
        );
    }

    public function is_authorized( WP_REST_Request $request ): bool {
        $nonce = $request->get_header( 'X-WP-Nonce' );

        return wp_verify_nonce( $nonce, 'wp_rest' ) && $this->get_connection_status_handler();
    }

    public function post_generate_auth_url(): WP_REST_Response {
        $this->api_key_manager->generate_csrf();

        $query_params = array(
            'fromPlugin' => true,
            'type'       => 'wordpress',
            'userType'   => $this->get_functions()->is_hostinger_user() ? 'internal' : 'external',
            'token'      => urlencode( $this->api_key_manager->get_csrf() ),
            'domain'     => $this->get_functions()->get_host_info(),
        );

        $reach_url = add_query_arg( $query_params, $this->reach_domain . 'settings/connect-site' );
        $auth_url  = add_query_arg(
            array(
                'redirectUrl' => urlencode( $reach_url ),
            ),
            $this->hostinger_auth_url
        );

        return new WP_REST_Response(
            array(
                'auth_url' => $auth_url,
                'success'  => true,
            ),
            200
        );
    }

    public function get_connection_status_handler(): bool {
        if ( ! $this->is_connected() ) {
            return false;
        }

        $status = $this->get( 'connection/status' );
        if ( is_wp_error( $status ) || wp_remote_retrieve_response_code( $status ) >= 400 ) {
            return false;
        }

        return true;
    }

    public function post_token_handler( WP_REST_Request $request ): WP_REST_Response {
        $csrf_field = $request->get_param( 'csrf_field' );
        $token      = $request->get_param( 'token' );
        if ( ! $this->api_key_manager->validate_csrf( $csrf_field ) ) {
            return $this->handle_wp_error( new WP_Error( $this->get_not_connected_error_message(), 'You cannot perform this action' ) );
        }

        $this->api_key_manager->store_token( $token );
        $this->api_key_manager->clear_csrf();

        if ( ! $this->get_connection_status_handler() ) {
            $this->api_key_manager->clear_token();

            return new WP_REST_Response( array( 'success' => false ) );
        }

        $this->set_connection_success_transient();

        return new WP_REST_Response( array( 'success' => true ) );
    }

    public function post_connect_handler(): WP_REST_Response {
        if ( ! $this->get_connection_status_handler() ) {
            return new WP_REST_Response( array( 'success' => false ), 400 );
        }

        $domain = apply_filters( 'hostinger_reach_domain', parse_url( get_option( 'siteurl' ), PHP_URL_HOST ) );

        $this->delete(
            'websites/connect',
            array(
                'domain' => $domain,
            )
        );

        $response = $this->post(
            'websites/connect',
            array(
                'domain' => $domain,
                'type'   => 'wordpress',
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->api_key_manager->clear_token();

            return $this->handle_wp_error( $response );
        }

        if ( ! isset( $response['response']['code'] ) || $response['response']['code'] >= 300 ) {
            $this->api_key_manager->clear_token();

            return new WP_REST_Response(
                array(
                    'success' => false,
                    'data'    => $response['response']['message'] ?? __( 'Error connecting your site', 'hostinger-reach' ),
                ),
                $response['response']['code'] ?? 400,
            );
        }

        $this->set_connection_success_transient();

        return new WP_REST_Response( array( 'success' => true ) );
    }

    public function get_connection_success_handler(): WP_REST_Response {
        return new WP_REST_Response( array( 'success' => (bool) get_transient( self::CONNECTION_SUCCESS_TRANSIENT ) ) );
    }

    public function delete_connection_success_handler(): WP_REST_Response {
        delete_transient( self::CONNECTION_SUCCESS_TRANSIENT );

        return new WP_REST_Response( array( 'success' => true ) );
    }

    public function get_overview_handler(): WP_REST_Response {
        if ( ! $this->get_connection_status_handler() ) {
            $this->api_key_manager->clear_token();
            $this->resource_id_manager->clear_resource_id();

            return $this->handle_wp_error( new WP_Error( $this->get_not_connected_error_message(), 'You cannot perform this action', array( 'status' => 403 ) ) );
        }

        $response = $this->get( 'overview' );

        if ( is_wp_error( $response ) ) {
            return $this->handle_wp_error( $response );
        }

        return $this->handle_response( $response );
    }

    public function post_contact( array $data ): WP_REST_Response {
        if ( ! $this->get_connection_status_handler() ) {
            return $this->handle_wp_error( new WP_Error( $this->get_not_connected_error_message(), 'You cannot perform this action' ) );
        }

        $contact    = $this->parse_contact( $data );
        $group_name = $data['group'] ? $data['group'] : HOSTINGER_REACH_DEFAULT_CONTACT_LIST;
        $tag_ids    = $this->get_tag_ids( $data['tags'] ?? '', $group_name );

        if ( empty( $tag_ids ) ) {
            $group_tag = $this->create_tag_from_group( $group_name );
            if ( ! empty( $group_tag ) ) {
                $tag_ids[] = $group_tag;
            }
        }

        $args = array(
            'tagUuids'  => $tag_ids,
            'groupName' => $group_name,
            'contacts'  => array( $contact ),
        );

        $response = $this->post(
            'contacts',
            $args
        );

        if ( is_wp_error( $response ) ) {
            do_action( 'hostinger_reach_contact_failed', $data );

            return $this->handle_wp_error( $response );
        }

        do_action( 'hostinger_reach_contact_submitted', $data );

        return $this->handle_response( $response );
    }

    public function post_import_contacts( array $contacts_data ): WP_REST_Response {
        if ( ! $this->get_connection_status_handler() ) {
            return $this->handle_wp_error( new WP_Error( $this->get_not_connected_error_message(), 'You cannot perform this action' ) );
        }

        $group = $contacts_data[0]['metadata']['group'] ?? HOSTINGER_REACH_DEFAULT_CONTACT_LIST;

        $contacts = array();
        foreach ( $contacts_data as $contact_data ) {
            $contacts[] = $this->parse_contact( $contact_data );
        }

        $args = array(
            'groupName' => $group,
            'contacts'  => $contacts,
        );

        $response = $this->post(
            'contacts',
            $args
        );

        if ( is_wp_error( $response ) ) {
            do_action( 'hostinger_reach_imports_contact_failed' );

            return $this->handle_wp_error( $response );
        }

        do_action( 'hostinger_reach_contacts_imported', count( $contacts ), $group );

        return $this->handle_response( $response );
    }

    public function post_webhook_event( array $webhook_payload ): WP_REST_Response {
        if ( ! $this->get_connection_status_handler() ) {
            return $this->handle_wp_error( new WP_Error( $this->get_not_connected_error_message(), 'You cannot perform this action' ) );
        }

        if ( ! isset( $webhook_payload['name'] ) ) {
            return $this->handle_wp_error( new WP_Error( 'Bad request', 'Missing parameter [name] in the WebHook data' ) );
        }

        if ( ! isset( $webhook_payload['contact']['email'] ) ) {
            return $this->handle_wp_error( new WP_Error( 'Bad request', 'Missing parameter [contact.email] in the WebHook data' ) );
        }

        $webhook_payload['timestamp'] = gmdate( 'Y-m-d\TH:i:s\Z' );

        $response = $this->post(
            'webhooks',
            $webhook_payload
        );

        if ( is_wp_error( $response ) ) {
            return $this->handle_wp_error( $response );
        }

        return $this->handle_response( $response );
    }

    public function get_tags_handler(): WP_REST_Response {
        if ( ! $this->get_connection_status_handler() ) {

            return $this->handle_wp_error( new WP_Error( $this->get_not_connected_error_message(), 'You cannot perform this action', array( 'status' => 403 ) ) );
        }

        $response = $this->get( 'tags' );

        if ( is_wp_error( $response ) ) {
            return $this->handle_wp_error( $response );
        }

        return $this->handle_response( $response );
    }

    public function post_tags_handler( WP_REST_Request $request ): WP_REST_Response {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) || ! $this->get_connection_status_handler() ) {
            return $this->handle_wp_error( new WP_Error( $this->get_not_connected_error_message(), 'You cannot perform this action', array( 'status' => 403 ) ) );
        }

        $names = $request->get_param( 'names' );
        if ( empty( $names ) ) {
            return $this->handle_wp_error( new WP_Error( 'names_empty', 'Names parameter cannot be empty.' ) );
        }

        $response = $this->post(
            'tags',
            array(
                'names' => $names,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $this->handle_wp_error( $response );
        }

        return $this->handle_response( $response );
    }

    private function parse_contact( array $data ): array {
        $contact = array(
            'email' => sanitize_email( $data['email'] ),
        );

        if ( ! empty( $data['name'] ) ) {
            $contact['name'] = $this->ensure_utf8( (string) $data['name'] );
        }

        if ( ! empty( $data['surname'] ) ) {
            $contact['surname'] = $this->ensure_utf8( (string) $data['surname'] );
        }

        $metadata = $data['metadata'] ?? array();
        if ( ! is_array( $metadata ) ) {
            $metadata = array();
        }

        // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- Internal metadata key.
        $metadata['platform'] = 'wordpress';

        if ( ! isset( $metadata['plugin'] ) ) {
            $metadata['plugin'] = ReachFormIntegration::INTEGRATION_NAME;
        }

        $contact['metadata'] = $metadata;

        return $contact;
    }

    public function get_forms_handler(): WP_REST_Response {
        if ( ! $this->get_connection_status_handler() ) {
            return $this->handle_wp_error( new WP_Error( $this->get_not_connected_error_message(), 'You cannot perform this action', array( 'status' => 403 ) ) );
        }

        $response = $this->get( 'forms' );

        if ( is_wp_error( $response ) ) {
            return $this->handle_wp_error( $response );
        }

        return $this->handle_response( $response );
    }

    public function get_form_preview_handler( WP_REST_Request $request ): WP_REST_Response {
        if ( ! $this->get_connection_status_handler() ) {

            return $this->handle_wp_error( new WP_Error( $this->get_not_connected_error_message(), 'You cannot perform this action', array( 'status' => 403 ) ) );
        }
        $form_id  = $request->get_param( 'id' );
        $response = $this->get( 'forms/' . $form_id . '/preview' );

        if ( is_wp_error( $response ) ) {
            return $this->handle_wp_error( $response );
        }

        if ( empty( wp_remote_retrieve_body( $response ) ) ) {
            return new WP_REST_Response( array( 'error' => 'preview_not_ready' ), 404 );
        }

        return $this->handle_image_response( $response );
    }

    private function ensure_utf8( string $value ): string {
        if ( $value === '' || ! function_exists( 'mb_check_encoding' ) || mb_check_encoding( $value, 'UTF-8' ) ) {
            return $value;
        }

        $detected  = mb_detect_encoding( $value, array( 'UTF-8', 'Windows-1252', 'ISO-8859-1' ), true );
        $converted = mb_convert_encoding( $value, 'UTF-8', $detected !== false ? $detected : 'ISO-8859-1' );

        return is_string( $converted ) ? $converted : $value;
    }

    private function set_connection_success_transient(): void {
        set_transient( self::CONNECTION_SUCCESS_TRANSIENT, true, self::CONNECTION_SUCCESS_TRANSIENT_EXPIRATION );
    }

    private function set_api_base_name(): void {
        if ( $this->get_functions()->is_staging() ) {
            $this->hostinger_auth_url = 'https://auth.hostinger.dev/login';
            $this->reach_domain       = 'https://reach.hostinger.dev/';
        } else {
            $this->hostinger_auth_url = 'https://auth.hostinger.com/login';
            $this->reach_domain       = 'https://reach.hostinger.com/';
        }

        $this->api_base_name = $this->reach_domain . 'api/public/v1/';
    }

    private function get_tag_ids( string $tag_names, string $group_name ): array {
        if ( empty( $tag_names ) && empty( $group_name ) ) {
            return array();
        }

        $tag_names = explode( ',', $tag_names );
        $tag_ids   = array();
        $handler   = $this->get_tags_handler();
        $tags_data = $handler->get_data();
        $tags      = $tags_data['data'] ?? array();

        foreach ( $tags as $tag ) {
            if ( in_array( $tag['value'], $tag_names, true ) || $tag['value'] === $group_name ) {
                $tag_ids[] = $tag['uuid'];
            }
        }

        return $tag_ids;
    }

    private function create_tag_from_group( string $group ): string {
        if ( empty( $group ) ) {
            return '';
        }

        $response = $this->post(
            'tags',
            array(
                'names' => array( $group ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $body = wp_remote_retrieve_body( $response );
        $body = json_decode( $body, true );

        return $body['data'][0]['uuid'] ?? '';
    }
}
