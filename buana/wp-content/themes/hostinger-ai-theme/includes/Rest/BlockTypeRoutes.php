<?php

namespace Hostinger\AiTheme\Rest;

use Alley\WP\Block_Converter\Block_Converter;
use Hostinger\AiTheme\Builder\BlockTypeDeterminer;
use Hostinger\AiTheme\Builder\DomainResolver;
use Hostinger\AiTheme\Builder\PageTypeDeterminer;
use Hostinger\AiTheme\Builder\Structure;
use Hostinger\AiTheme\Builder\ContentParser;
use Hostinger\AiTheme\Builder\RequestClient;
use Hostinger\AiTheme\Constants\BuilderType;
use Hostinger\AiTheme\Data\WebsiteTypeHelper;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\AiTheme\Rest\Endpoints;
use Hostinger\Amplitude\AmplitudeManager;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Utils;
use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Http;

defined( 'ABSPATH' ) || exit;

/**
 * Class BlockTypeRoutes
 * Handles REST routes for AI-powered block type determination and content generation
 */
class BlockTypeRoutes {
    const AMPLITUDE_EVENT_NAME = 'wordpress.ai_builder.content.create';
    private const AMPLITUDE_EVENT_FAILURE = 'wordpress.ai_builder.failure';
    private BlockTypeDeterminer $block_type_determiner;
    private PageTypeDeterminer $page_type_determiner;
    private Structure $structure;
    private RequestClient $request_client;
    private Client $client;
    private AmplitudeManager $amplitude_manager;
    private DomainResolver $domain_resolver;

    public function __construct() {
        $this->initialize_services();
    }

    private function initialize_services(): void {
        $helper         = new Utils();
        $config_handler = new Config();
        $base_uri       = $config_handler->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI );

        $base_headers = [
            Config::TOKEN_HEADER  => $helper::getApiToken(),
            Config::DOMAIN_HEADER => $helper->getHostInfo(),
        ];

        $client = new Client( $base_uri, array_merge( $base_headers, [
            'Content-Type' => 'application/json',
        ] ) );

        $amplitude_client = new Client( $base_uri, $base_headers );

        $this->domain_resolver       = new DomainResolver( $helper );
        $this->amplitude_manager     = new AmplitudeManager( $helper, $config_handler, $amplitude_client );
        $this->block_type_determiner = new BlockTypeDeterminer( $client, $this->domain_resolver, $helper );
        $this->page_type_determiner  = new PageTypeDeterminer( $client, $this->domain_resolver, $helper );

        $this->client         = $client;
        $this->request_client = new RequestClient( $client );

        $website_type        = WebsiteTypeHelper::get_website_types();
        $website_description = get_option( 'hostinger_ai_description', get_bloginfo( 'description' ) );
        $brand_name          = get_option( 'hostinger_ai_brand_name', get_bloginfo( 'name' ) );

        $this->structure = new Structure( $brand_name, $website_type, $website_description );
        $this->structure->set_request_client( $this->request_client );
    }

    public function generate_section( WP_REST_Request $request ): WP_REST_Response | WP_Error {
        try {
            $description    = $this->validate_and_get_description( $request );
            $block_type     = $this->determine_block_type( $description );
            $structure_data = $this->generate_structure_data( $block_type );
            $content        = $this->generate_and_merge_content_with_description( $structure_data, $description );

            $this->amplitude_manager->sendRequest( Endpoints::AMPLITUDE_ENDPOINT, [
                'action' => self::AMPLITUDE_EVENT_NAME,
                'type'   => 'section',
            ] );

            return new WP_REST_Response( [
                'blockType' => $block_type['blockType'],
                'content'   => $content,
            ] );
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log(sprintf(
                    '[AI_THEME_SECTION_ERROR] Error: %s | Description: %s',
                    $e->getMessage(),
                    substr($description ?? 'N/A', 0, 100) . (strlen($description ?? '') > 100 ? '...' : '')
                ));
            }

            $this->send_failure_event( 'section_generation', 'ai_endpoint', $e->getMessage() );

            $error_message = __( 'Failed to generate section content. Please try again with a different description.', 'hostinger-ai-theme' );
            return new WP_Error( 'section_generation_failed', $error_message, [
                'status' => WP_Http::INTERNAL_SERVER_ERROR,
                'error'  => $e->getMessage(),
            ] );
        }
    }

    public function generate_page( WP_REST_Request $request ): WP_REST_Response | WP_Error {
        try {
            $parameters = $request->get_params();

            if ( empty( $parameters['description'] ) ) {
                throw new Exception( __( 'Description is required.', 'hostinger-ai-theme' ) );
            }

            $description = sanitize_text_field( $parameters['description'] );

            // Determine page type
            $page_type_result = $this->page_type_determiner->determine_page_type( $description );
            if ( ! $page_type_result['success'] ) {
                throw new Exception( $page_type_result['error'] );
            }

            $page_name = $page_type_result['pageType'];

            $website_type = WebsiteTypeHelper::get_website_types();
            $brand_name   = get_option( 'hostinger_ai_brand_name', get_bloginfo( 'name' ) );

            // Create a new Structure instance with the request description, forcing Gutenberg builder type for REST API
            $structure_with_request_description = new Structure( $brand_name, $website_type, $description, BuilderType::GUTENBERG );
            $structure_with_request_description->set_request_client( $this->request_client );

            // Generate structure for the specific page
            $page_structure = $structure_with_request_description->generate_page_structure( $website_type, $page_name );

            if ( empty( $page_structure ) ) {
                throw new Exception( __( 'Failed to generate page structure.', 'hostinger-ai-theme' ) );
            }

            // Generate content for the page using the structure with request description
            $builder_data = $structure_with_request_description->generate_builder_data( $page_structure );
            $content      = $structure_with_request_description->generate_content( $builder_data );

            if ( empty( $content ) || empty( $content['pages'] ) ) {
                throw new Exception( __( 'Failed to generate page content.', 'hostinger-ai-theme' ) );
            }

            // Merge content with structure
            $final_content = $structure_with_request_description->merge_content( $builder_data, $content );

            // Parse content to HTML
            $page_html = '';
            if ( ! empty( $final_content['pages'][$page_name]['sections'] ) ) {
                foreach ( $final_content['pages'][$page_name]['sections'] as $section ) {
                    // Always use Gutenberg format for REST API endpoints (Gutenberg blocks)
                    $content_parser = new ContentParser( $section, BuilderType::GUTENBERG );
                    $page_html      .= $content_parser->output();
                }
            }
            $this->amplitude_manager->sendRequest( Endpoints::AMPLITUDE_ENDPOINT, [
                'action' => self::AMPLITUDE_EVENT_NAME,
                'type'   => 'page',
            ] );

            return new WP_REST_Response( [
                'pageName' => $page_name,
                'content'  => $page_html,
            ] );
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log(sprintf(
                    '[AI_THEME_PAGE_ERROR] Error: %s | Description: %s | Page Name: %s',
                    $e->getMessage(),
                    substr($description ?? 'N/A', 0, 100) . (strlen($description ?? '') > 100 ? '...' : ''),
                    $page_name ?? 'N/A'
                ));
            }

            $this->send_failure_event( 'page_generation', 'ai_endpoint', $e->getMessage() );

            $error_message = __( 'Failed to generate page content. Please try again with a different description.', 'hostinger-ai-theme' );
            return new WP_Error( 'page_generation_failed', $error_message, [
                'status' => WP_Http::INTERNAL_SERVER_ERROR,
                'error'  => $e->getMessage(),
            ] );
        }
    }

    public function generate_content( WP_REST_Request $request ): WP_REST_Response | WP_Error {
        try {
            $description = $this->validate_and_get_description( $request );
            $parameters  = $this->get_content_parameters( $request );

            if ( empty( $description ) ) {
                return new WP_Error( 'missing_description', 'Description is required' );
            }

            $generated_content = $this->fetch_content_from_api( $description, $parameters );

            if ( $parameters['add_images'] ) {
                $generated_content = $this->append_images_if_available( $generated_content );
            }

            if ( ! empty( $generated_content['content'] ) ) {
                $generated_content['content'] = $this->convertToBlocks( $generated_content );
            }

            $this->amplitude_manager->sendRequest( Endpoints::AMPLITUDE_ENDPOINT, [
                'action' => self::AMPLITUDE_EVENT_NAME,
                'type'   => 'content',
            ] );

            return new WP_REST_Response( [
                'content' => $generated_content['content'],
            ] );
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log(sprintf(
                    '[AI_THEME_CONTENT_ERROR] Error: %s | Description: %s | Parameters: %s',
                    $e->getMessage(),
                    substr($description ?? 'N/A', 0, 100) . (strlen($description ?? '') > 100 ? '...' : ''),
                    json_encode($parameters ?? [])
                ));
            }

            $this->send_failure_event( 'content_generation', 'ai_endpoint', $e->getMessage() );

            $error_message = __( 'Failed to generate content. Please try again with a different description.', 'hostinger-ai-theme' );
            return new WP_Error( 'content_generation_failed', $error_message, [
                'status' => WP_Http::INTERNAL_SERVER_ERROR,
                'error'  => $e->getMessage(),
            ] );
        }
    }

    private function get_content_parameters( WP_REST_Request $request ): array {
        $parameters = $request->get_params();

        return [
            'post_type'      => $this->validate_post_type( $parameters['post_type'] ),
            'tone_of_voice'  => $parameters['tone_of_voice'] ?? 'neutral',
            'content_length' => $this->validate_content_length( $parameters['content_length'] ),
            'add_images'     => $parameters['add_images'] ?? false,
        ];
    }

    private function validate_post_type( string $post_type ): string {
        return match ( $post_type ) {
            'post-type-post' => 'blog_post',
            'post-type-product' => 'product_description',
            default => 'page',
        };
    }

    private function validate_content_length( string $content_length ): string {
        return match ( $content_length ) {
            'medium' => '600-1200',
            'long' => '1500-2600',
            default => '150-300',
        };
    }

    private function fetch_content_from_api( string $description, array $parameters ): array {
        $data = [
            'post_type'   => $parameters['post_type'],
            'tone'        => $parameters['tone_of_voice'],
            'length'      => $parameters['content_length'],
            'description' => $description,
        ];

        $response      = $this->client->get( Endpoints::GENERATE_CONTENT_ENDPOINT, $data, [] );
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) || $response_code !== 200 ) {
            $error_message = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $response_code;
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log(sprintf('[AI_THEME_API_ERROR] Content generation API failed: %s | Response Code: %s', $error_message, $response_code));
            }
            throw new Exception( __( 'Content generation failed', 'hostinger-ai-theme' ) );
        }

        $body    = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );

        return reset( $decoded['data'] );
    }

    private function append_images_if_available( array $generated_content ): array {
        if ( empty( $generated_content['tags'][0] ) ) {
            return $generated_content;
        }

        $image_data = [
            'domain'      => $this->domain_resolver->get_current_domain(),
            'description' => $generated_content['tags'][0],
            'limit'       => count( $generated_content['tags'] ?? [] ),
        ];

        $image_response = $this->client->post( Endpoints::GENERATE_IMAGE_ENDPOINT, json_encode( $image_data ) );

        if ( is_wp_error( $image_response ) || wp_remote_retrieve_response_code( $image_response ) !== 200 ) {
            return $generated_content;
        }

        $images                      = json_decode( wp_remote_retrieve_body( $image_response ), true );
        $generated_content['images'] = $images['data']['list'] ?? [];

        return $generated_content;
    }

    private function convertToBlocks( array $generated_content ): string {
        $converter         = new Block_Converter( $generated_content['content'] );
        $converted_content = $converter->convert();

        // Insert images if available
        if ( ! empty( $generated_content['images'] ) ) {
            $converted_content = $this->integrateImagesIntoContent( $converted_content, $generated_content['images'] );
        }

        return $converted_content;
    }

    private function integrateImagesIntoContent( string $content, array $images ): string {

        if ( empty( $images ) ) {
            return $content;
        }

        $blocks           = parse_blocks( $content );
        $modified_content = '';
        $image_index      = 0;
        $image_count      = count( $images );

        // For every 3-4 blocks, insert an image if available
        $image_insertion_frequency = 3;
        $block_count               = count( $blocks );

        for ( $i = 0; $i < $block_count; $i++ ) {
            $modified_content .= serialize_block( $blocks[$i] );

            if ( $image_index < $image_count && ( $i + 1 ) % $image_insertion_frequency === 0 && $i < $block_count - 1 ) {
                $image_url = $images[$image_index]['photo_image_url'] ?? $images[$image_index]['photo_image_url'] ?? '';
                if ( ! empty( $image_url ) ) {
                    $image_block      = '<!-- wp:image {"sizeSlug":"large"} -->
            <figure class="wp-block-image size-large"><img src="' . esc_url( $image_url ) . '" alt=""/></figure>
            <!-- /wp:image -->';
                    $modified_content .= $image_block;
                    $image_index++;
                }
            }
        }

        return $modified_content;
    }

    private function validate_and_get_description( WP_REST_Request $request ): string {
        $parameters = $request->get_params();

        if ( empty( $parameters['description'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log('[AI_THEME_VALIDATION_ERROR] Missing description parameter in request');
            }
            throw new Exception( __( 'Description is required.', 'hostinger-ai-theme' ) );
        }

        return sanitize_text_field( $parameters['description'] );
    }

    /**
     * Determine block type from description
     *
     * @param string $description User provided description
     *
     * @return array Block type data
     * @throws Exception If block type determination fails
     */
    private function determine_block_type( string $description ): array {
        $block_type = $this->block_type_determiner->determine_block_type( $description );

        if ( ! is_array( $block_type ) || ! isset( $block_type['success'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log(sprintf('[AI_THEME_BLOCK_TYPE_ERROR] Invalid block type response: %s', json_encode($block_type)));
            }
            throw new Exception( __( 'Invalid block type response.', 'hostinger-ai-theme' ) );
        }

        if ( ! $block_type['success'] ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log(sprintf('[AI_THEME_BLOCK_TYPE_ERROR] Block type determination failed: %s', $block_type['error']));
            }
            throw new Exception( $block_type['error'] );
        }

        return $block_type;
    }

    private function generate_structure_data( array $block_type ): array {
        return [
            [
                'page'     => '',
                'sections' => [
                    [
                        'id'      => uniqid(),
                        'section' => $block_type['blockType'],
                    ],
                ],
            ],
        ];
    }

    private function generate_and_merge_content_with_description( array $structure_data, string $description ): string {
        // Create a new Structure instance with the description, forcing Gutenberg builder type for REST API
        $brand_name = get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'Brand';
        $structure_with_request_description = new Structure( $brand_name, WebsiteTypeHelper::get_website_types(), $description, BuilderType::GUTENBERG );
        $structure_with_request_description->set_request_client( $this->request_client );

        // Generate builder data using the provided structure_data
        $builder_data = $structure_with_request_description->generate_builder_data( $structure_data );

        // Generate content using AI
        $content = $structure_with_request_description->generate_content( $builder_data );
        if ( empty( $content ) || empty( $content['pages'] ) ) {
            throw new Exception( __( 'Failed to generate section content.', 'hostinger-ai-theme' ) );
        }

        // Merge content with structure
        $final_content = $structure_with_request_description->merge_content( $builder_data, $content );
        if ( empty( $final_content ) || empty( $final_content['pages'] ) ) {
            throw new Exception( __( 'Failed to merge content with structure.', 'hostinger-ai-theme' ) );
        }

        return $this->parse_content_to_html( $final_content );
    }

    private function parse_content_to_html( array $final_content ): string {
        $section_html = '';

        if ( ! empty( $final_content['pages'] ) && ! empty( $final_content['pages'][''] ) && ! empty( $final_content['pages']['']['sections'] ) ) {
            foreach ( $final_content['pages']['']['sections'] as $section ) {
                // Always use Gutenberg format for REST API endpoints (Gutenberg blocks)
                $content_parser = new ContentParser( $section, BuilderType::GUTENBERG );
                $section_html  .= $content_parser->output();
            }
        }

        return $section_html;
    }

    /**
     * Send amplitude failure event with context.
     *
     * @param string $failed_step   The step that failed
     * @param string $failure_type  The category of failure
     * @param string $error_message The error message (will be sanitized)
     */
    private function send_failure_event( string $failed_step, string $failure_type, string $error_message ): void {
        $this->amplitude_manager->sendRequest( Endpoints::AMPLITUDE_ENDPOINT, array(
            'action'        => self::AMPLITUDE_EVENT_FAILURE,
            'failed_step'   => $failed_step,
            'failure_type'  => $failure_type,
            'error_message' => $this->sanitize_error_message( $error_message ),
            'builder_type'  => get_option( 'hostinger_ai_builder_type', 'unknown' ),
            'website_type'  => get_option( 'hostinger_ai_website_type', 'unknown' ),
            'theme_version' => wp_get_theme()->get( 'Version' ),
        ) );
    }

    /**
     * Sanitize error message for amplitude tracking.
     *
     * @param string $error_message
     *
     * @return string
     */
    private function sanitize_error_message( string $error_message ): string {
        $sanitized = preg_replace( '#(/[a-zA-Z0-9._-]+){3,}#', '[path_redacted]', $error_message );
        $sanitized = preg_replace( '/[a-zA-Z0-9]{32,}/', '[token_redacted]', $sanitized );

        if ( strlen( $sanitized ) > 500 ) {
            $sanitized = substr( $sanitized, 0, 500 ) . '...';
        }

        return $sanitized;
    }
}
