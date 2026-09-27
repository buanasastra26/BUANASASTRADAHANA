<?php

namespace Hostinger\EasyOnboarding\Rest;

use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Utils as Helper;
use Hostinger\WpHelper\Requests\Client;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class HostingRoutes {
    private $client;
    private $config;
    private $helper;

    /**
     * @param Client $client
     * @param Helper $helper
     */
    public function __construct( Helper $helper ) {
        $this->helper = $helper;
        $this->config = new Config();
        $this->client = new Client(
            $this->config->getConfigValue( 'base_rest_uri', HOSTINGER_EASY_ONBOARDING_REST_URI ),
            array(
                Config::TOKEN_HEADER  => $this->helper->getApiToken(),
                Config::DOMAIN_HEADER => $this->helper->getHostInfo(),
            )
        );
    }

    private function make_api_request( string $endpoint, array $params = array(), string $error_prefix = 'Hostinger Easy Onboarding' ): \WP_REST_Response {
        $data     = array(
            'status' => 'error',
            'data'   => array(),
        );
        $response = new \WP_REST_Response();

        try {
            $response->set_status( \WP_Http::OK );

            $request = $this->client->get( $endpoint, $params );

            if ( is_wp_error( $request ) ) {
                throw new \Exception( $request->get_error_message() );
            }

            if ( ! empty( $request['body'] ) ) {
                $json = json_decode( $request['body'], true );

                if ( ! empty( $json['data'] ) ) {
                    $data = array(
                        'status' => 'success',
                        'data'   => $json['data'],
                    );
                }
            }
        } catch ( \Exception $exception ) {
            $response->set_status( \WP_Http::BAD_REQUEST );

            $this->helper->errorLog( "$error_prefix: Error sending request: " . $exception->getMessage() );

            $data = array(
                'message' => $exception->getMessage(),
            );
        }

        $response->set_data( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );

        return $response;
    }

    /**
     * Get plan details from Hostinger API
     *
     * @return \WP_REST_Response
     */
    public function get_hosting_details(): \WP_REST_Response {
        global $wpdb;
        $siteurl = get_option( 'siteurl', $this->helper->getHostInfo() );
        $domain  = parse_url( $siteurl, PHP_URL_HOST );

        $request = $this->make_api_request( '/v3/wordpress/plan-details', array( 'db_name' => $wpdb->dbname ) );

        $data                            = $request->get_data();
        $data['data']['original_domain'] = implode( ' ', str_split( $domain ) );
        $request->set_data( $data );

        return $request;
    }

    /**
     * Get domain details from Hostinger API
     *
     * @return \WP_REST_Response
     */
    public function get_domain_details(): \WP_REST_Response {
        $siteurl = get_option( 'siteurl', $this->helper->getHostInfo() );
        $domain  = parse_url( $siteurl, PHP_URL_HOST );

        $request = $this->make_api_request( '/v3/wordpress/domain-details', array( 'domain' => $domain ) );

        $data                            = $request->get_data();
        $data['data']['original_domain'] = implode( ' ', str_split( $domain ) );
        $request->set_data( $data );

        return $request;
    }
}
