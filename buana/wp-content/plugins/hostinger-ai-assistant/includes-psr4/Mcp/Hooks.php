<?php

namespace Hostinger\AiAssistant\Mcp;

use Alley\WP\Block_Converter\Block_Converter;
use Exception;
use stdClass;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class Hooks {
    public function init(): void {
        add_filter( 'hostinger_once_per_day_events', array( $this, 'limit_triggered_amplitude_events' ) );

        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if ( ! str_contains( $current_url, HOSTINGER_AI_ASSISTANT_REST_API_BASE . '/mcp' ) ) {
            return;
        }

        add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'filter_product_meta_fields' ), 10, 3 );
        add_filter( 'rest_pre_insert_post', array( $this, 'convert_content_to_blocks' ), 10, 2 );
    }

    public function filter_product_meta_fields( WP_REST_Response $response, mixed $post, WP_REST_Request $request ): WP_REST_Response {
        if ( isset( $response->data['meta_data'] ) && is_array( $response->data['meta_data'] ) ) {
            $response->data['meta_data'] = array_values(
                array_filter(
                    $response->data['meta_data'],
                    function ( $meta ) {
                        return ! str_starts_with( $meta->key, '_uag' );
                    }
                )
            );
        }

        return $response;
    }

    public function limit_triggered_amplitude_events( array $events ): array {
        $new_events = array(
            'wordpress.chatbot.survey_filled',
        );

        return array_merge( $events, $new_events );
    }

    public function convert_content_to_blocks( stdClass $prepared_post, WP_REST_Request $request ): stdClass|WP_Error {
        if ( empty( $prepared_post->post_content ) ) {
            return $prepared_post;
        }

        $post_type = $prepared_post->post_type ?? 'post';

        if ( ! use_block_editor_for_post_type( $post_type ) ) {
            return $prepared_post;
        }

        if ( ContentUtils::is_block_content( $prepared_post->post_content ) ) {
            return $prepared_post;
        }

        try {
            $converter                   = new Block_Converter( $prepared_post->post_content );
            $prepared_post->post_content = $converter->convert();
        } catch ( Exception $e ) {
            error_log( 'MCP Block conversion error: ' . $e->getMessage() );
        }

        return $prepared_post;
    }
}
