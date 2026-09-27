<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils as Helper;
use Hostinger\AiTheme\Rest\Endpoints;

defined( 'ABSPATH' ) || exit;

class PageTypeDeterminer {
    private Client $client;
    private DomainResolver $domain_resolver;

    public function __construct( Client $client, ?DomainResolver $domain_resolver = null, ?Helper $helper = null ) {
        $this->client = $client;
        $helper = $helper ?? new Helper();
        $this->domain_resolver = $domain_resolver ?? new DomainResolver( $helper );
    }

    public function determine_page_type( string $description ): array {
        $system_message = [
            'role'    => 'system',
            'content' => 'Using provided page description determine single-word page type. '
                         . 'Restriction: if description is about ecommerce store, page type should be "store". '
                         . 'Important: output must only be following json format object: { "pageType": "pagetype" }',
        ];

        $user_message = [
            'role'    => 'user',
            'content' => $description,
        ];

        $domain = $this->domain_resolver->get_current_domain();

        $request_body = [
            'domain'   => $domain,
            'messages' => [ $system_message, $user_message ],
        ];

        $response = $this->client->post( Endpoints::GENERATE_BLOCKS_ENDPOINT, json_encode( $request_body ) );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            return [
                'success' => false,
                'error'   => 'Invalid response from AI service',
            ];
        }

        $body        = json_decode( wp_remote_retrieve_body( $response ), true );
        $contentData = json_decode( $body['data']['content'], true );
        $pageType    = $contentData['pageType'];

        if ( empty( $pageType ) ) {
            return [
                'success' => false,
                'error'   => 'No page type determined',
            ];
        }

        return [
            'success'  => true,
            'pageType' => $pageType,
        ];
    }
}