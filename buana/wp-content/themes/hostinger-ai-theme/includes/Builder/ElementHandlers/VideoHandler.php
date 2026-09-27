<?php

namespace Hostinger\AiTheme\Builder\ElementHandlers;

use Hostinger\AiTheme\Builder\VideoManager;
use DOMElement;

defined( 'ABSPATH' ) || exit;

class VideoHandler extends BaseElementHandler {
    /**
     * Handle video elements in Gutenberg blocks
     *
     * @param DOMElement $node
     * @param array $element_structure
     * @return void
     */
    public function handle_gutenberg( DOMElement &$node, array $element_structure ): void {
        $video_data = $this->prepare_video_data( $element_structure );
        if ( empty( $video_data ) ) {
            return;
        }

        $videos = $node->getElementsByTagName( 'video' );

        if ( $videos->length > 0 ) {
            $video = $videos->item( 0 );
            $video->setAttribute( 'src', $video_data['url'] );
            $video->setAttribute( 'preload', 'metadata' );

            if ( ! empty( $video_data['thumbnail'] ) ) {
                $video->setAttribute( 'poster', $video_data['thumbnail'] );
            }

            if ( $video->hasAttribute( 'data-object-fit' ) ) {
                $video->setAttribute( 'data-object-fit', 'cover' );
            }
        }

        $this->update_cover_block_video_url( $node, $video_data['url'] );
    }

    /**
     * Handle video elements in Elementor blocks
     *
     * @param array $element
     * @param array $element_structure
     * @return void
     */
    public function handle_elementor( array &$element, array $element_structure ): void {
        if ( empty( $element['settings'] ) ) {
            return;
        }

        $video_data = $this->prepare_video_data( $element_structure );
        if ( empty( $video_data ) ) {
            return;
        }

        // Handle video background in section/container
        if ( isset( $element['settings']['background_video_link'] ) ) {
            $element['settings']['background_video_link'] = $video_data['url'];
        }

        // Handle video widget
        if ( isset( $element['widgetType'] ) && $element['widgetType'] === 'video' ) {
            $element['settings']['youtube_url'] = '';
            $element['settings']['vimeo_url'] = '';
            $element['settings']['hosted_url'] = [
                'url' => $video_data['url'],
            ];
            $element['settings']['video_type'] = 'hosted';
        }

        // Handle background video for sections/containers
        if ( isset( $element['elType'] ) && in_array( $element['elType'], [ 'section', 'container' ], true ) ) {
            $element['settings']['background_background']       = 'video';
            $element['settings']['background_video_link']      = $video_data['url'];
            $element['settings']['background_play_on_mobile']  = 'yes';
        }

        if ( ! empty( $video_data['thumbnail'] ) ) {
            $element['settings']['background_video_fallback'] = [
                'url'    => $video_data['thumbnail'],
                'id'     => '',
                'size'   => '',
                'source' => 'url',
            ];
        }
    }

    /**
     * Prepare video data from element structure
     *
     * @param array $element_structure
     * @return array|null
     */
    private function prepare_video_data( array $element_structure ): ?array {
        $content = $element_structure['default_content']
                   ?? $element_structure['content']
                      ?? '';

        if ( empty( $content ) ) {
            return null;
        }

        $website_description = get_option( 'hostinger_ai_description', '' );
        $video_context       = 'Video for ' . strip_tags( $content );

        if ( ! empty( $website_description ) ) {
            $video_context .= '. Website description: ' . strip_tags( $website_description );
        }

        $video_manager = new VideoManager( $video_context );
        $video_data    = $video_manager->get_video_data( ! empty( $video_context ) );

        if ( empty( $video_data ) || ! property_exists( $video_data, 'url' ) || empty( $video_data->url ) ) {
            return null;
        }

        return [
            'url'         => $video_data->url,
            'thumbnail'   => property_exists( $video_data, 'thumbnail' ) ? $video_data->thumbnail : '',
            'description' => property_exists( $video_data, 'description' ) ? $video_data->description : '',
        ];
    }

    /**
     * Update the video URL in wp:cover block comment
     *
     * @param DOMElement $node
     * @param string $video_url
     * @return void
     */
    private function update_cover_block_video_url( DOMElement $node, string $video_url ): void {
        $previousElement = $node->previousSibling;

        if ( ! $previousElement || $previousElement->nodeType !== XML_COMMENT_NODE ) {
            return;
        }

        $comment_value = $previousElement->nodeValue;

        // Check if this is a wp:cover block
        if ( strpos( $comment_value, 'wp:cover' ) === false ) {
            return;
        }

        // Extract the JSON from the comment
        $json_start = strpos( $comment_value, '{' );
        if ( $json_start === false ) {
            return;
        }

        $json_str = substr( $comment_value, $json_start );
        $block    = json_decode( $json_str, true );

        if ( empty( $block ) ) {
            return;
        }

        if ( isset( $block['backgroundType'] ) && $block['backgroundType'] === 'video' ) {
            $block['url'] = $video_url;
            unset( $block['backgroundImageUrl'] );

            $prefix       = substr( $comment_value, 0, $json_start );
            $previousElement->nodeValue = $prefix . json_encode( $block ) . ' ';
        }
    }
}

