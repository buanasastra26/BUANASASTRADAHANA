<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Data\SectionData;
use Hostinger\AiTheme\Data\WebsiteTypeHelper;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils as Helper;
use Hostinger\AiTheme\Builder\Helper as AiThemeHelper;
use Hostinger\AiTheme\Rest\Endpoints;

defined( 'ABSPATH' ) || exit;

class BlockTypeDeterminer {
    private Client $client;
    private DomainResolver $domain_resolver;
    private array $block_types;

    public function __construct( Client $client, ?DomainResolver $domain_resolver = null, ?Helper $helper = null ) {
        $this->client = $client;
        $helper = $helper ?? new Helper();
        $this->domain_resolver = $domain_resolver ?? new DomainResolver( $helper );
        $this->load_block_types();
    }

    private function load_block_types(): void {
        $website_type      = WebsiteTypeHelper::get_website_types();
        $this->block_types = SectionData::get_sections_for_website_type( $website_type, AiThemeHelper::should_render_india_version() );
    }

    public function determine_block_type( string $description ): array {
        if ( empty( $this->block_types ) ) {
            return [
                'success' => false,
                'error'   => 'Block types not loaded',
            ];
        }

        $system_message = [
            'role'    => 'system',
            'content' => 'You are a block type classifier. Your task is to analyze the user description and return the most appropriate block type from the following list: '
                         . json_encode( $this->block_types )
                         . ' Respond ONLY with a JSON object in this format: {"blockType": "blockType"}. Do not include any other text or explanation.',
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
        $blockType   = $contentData['blockType'];

        if ( ! isset( $blockType ) || ! array_key_exists( $blockType, $this->block_types ) ) {
            return [
                'success' => false,
                'error'   => 'Invalid block type returned',
            ];
        }

        return [
            'success'   => true,
            'blockType' => $blockType,
        ];
    }
}
