<?php

namespace Hostinger\AiAssistant\Chatbot;

use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ReachOutClient {
    private const INSTALLATIONS_ENDPOINT = '/installations';
    private const REACH_OUT_ENDPOINT     = '/installations/%s/chatbot/reach-out';
    private const SOFTWARE_ID_OPTION     = 'hostinger_sfid';
    private const SOFTWARE_ID_OVERRIDE   = 'HOSTINGER_SOFTWARE_ID_OVERRIDE';
    private const REQUEST_TIMEOUT        = 15;

    private Config $config;
    private Client $client;
    private string $domain;

    public function __construct( Config $config, Utils $utils ) {
        $this->config = $config;
        $this->domain = $utils->getHostInfo();
        $this->client = new Client(
            $config->getConfigValue( 'wp_proxy_ai_rest_uri', HOSTINGER_AI_ASSISTANT_WP_API_PROXY_URI ),
            array(
                Config::TOKEN_HEADER  => Utils::getApiToken(),
                Config::DOMAIN_HEADER => $this->domain,
                'Content-Type'        => 'application/json',
                'Accept'              => 'application/json',
            )
        );
    }

    /**
     * Register one or more proactive reach-outs for a given chatbot user.
     *
     * A single entry point so the caller dispatches every fired nudge in one go,
     * regardless of how many there are: the installation `software_id` is
     * resolved once for the whole set. The chatbot backend currently exposes a
     * per-reach-out endpoint, so we fan out here; once a true batch endpoint
     * exists, only this method's body changes to a single request.
     *
     * @param string $user_id Chatbot user id (matches the widget's localStorage userId).
     * @param array<string, array{message: string, visuals?: array<int, array<string, mixed>>}> $reach_outs
     *        Reach-outs keyed by campaign/name identifier agreed with the chatbots team.
     *
     * @return array<string, bool> Campaign => true only when the service accepted the outreach,
     *                             so callers avoid marking a nudge as sent on failure.
     */
    public function send_batch( string $user_id, array $reach_outs ): array {
        $results = array();

        if ( empty( $user_id ) || empty( $reach_outs ) ) {
            return $results;
        }

        $software_id = $this->get_software_id();

        foreach ( $reach_outs as $campaign => $reach_out ) {
            $results[ $campaign ] = $software_id !== '' && $this->dispatch_reach_out(
                $software_id,
                $user_id,
                (string) $campaign,
                (string) ( $reach_out['message'] ?? '' ),
                $reach_out['visuals'] ?? array()
            );
        }

        return $results;
    }

    /**
     * @param array<int, array<string, mixed>> $visuals
     */
    private function dispatch_reach_out( string $software_id, string $user_id, string $campaign, string $message, array $visuals ): bool {
        if ( $message === '' ) {
            return false;
        }

        $payload = array(
            'userId'  => $user_id,
            'message' => $message,
            'name'    => $campaign,
        );

        if ( ! empty( $visuals ) ) {
            $payload['visuals'] = $visuals;
        }

        $response = $this->client->post(
            sprintf( self::REACH_OUT_ENDPOINT, $software_id ),
            wp_json_encode( $payload ),
            array(),
            self::REQUEST_TIMEOUT
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return false;
        }

        $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        return ! is_array( $data ) || ( $data['success'] ?? true ) !== false;
    }

    private function get_software_id(): string {
        if ( defined( self::SOFTWARE_ID_OVERRIDE ) && ! empty( constant( self::SOFTWARE_ID_OVERRIDE ) ) ) {
            return (string) constant( self::SOFTWARE_ID_OVERRIDE );
        }

        $cached = get_option( self::SOFTWARE_ID_OPTION );
        if ( is_string( $cached ) && $cached !== '' ) {
            return $cached;
        }

        $config_software_id = $this->config->getConfigValue( 'software_id', '' );
        if ( $config_software_id !== '' ) {
            update_option( self::SOFTWARE_ID_OPTION, $config_software_id, true );

            return $config_software_id;
        }

        $response = $this->client->get(
            self::INSTALLATIONS_ENDPOINT,
            array( 'domain' => $this->domain ),
            array(),
            self::REQUEST_TIMEOUT
        );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return '';
        }

        $data        = json_decode( wp_remote_retrieve_body( $response ), true );
        $software_id = (string) ( $data['data'][0]['id'] ?? '' );

        if ( $software_id !== '' ) {
            update_option( self::SOFTWARE_ID_OPTION, $software_id, true );
        }

        return $software_id;
    }
}
