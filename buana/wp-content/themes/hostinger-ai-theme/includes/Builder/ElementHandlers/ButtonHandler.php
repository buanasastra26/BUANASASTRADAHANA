<?php

namespace Hostinger\AiTheme\Builder\ElementHandlers;

use DOMComment;
use DOMElement;
use DOMNode;

defined( 'ABSPATH' ) || exit;

class ButtonHandler extends BaseElementHandler {
    public function handle_gutenberg( DOMElement &$node, array $element_structure ): void {
        $links = $node->getElementsByTagName( 'a' );

        if ( $links->length === 0 ) {
            return;
        }

        $url = $element_structure['link'] ?? home_url( '/' );

        $link            = $links->item( 0 );
        $link->nodeValue = $element_structure['content'];
        $link->setAttribute( 'href', $url );
        $this->sync_block_url( $node, $url );
    }

    private function sync_block_url( DOMElement $node, string $url ): void {
        $comment = $this->find_preceding_button_comment( $node );

        if ( ! $comment instanceof DOMComment ) {
            return;
        }

        $attributes = json_decode( str_replace( ' wp:button ', '', $comment->nodeValue ), true );
        if ( ! is_array( $attributes ) ) {
            $attributes = array();
        }

        $attributes['url'] = $url;
        $comment->nodeValue = ' wp:button ' . json_encode( $attributes ) . ' ';
    }

    private function find_preceding_button_comment( DOMElement $node ): ?DOMComment {
        $sibling = $node->previousSibling;

        while ( $sibling instanceof DOMNode ) {
            if ( $sibling instanceof DOMComment
                 && str_contains( $sibling->nodeValue, 'wp:button' )
                 && ! str_contains( $sibling->nodeValue, '/wp:button' ) ) {
                return $sibling;
            }

            $sibling = $sibling->previousSibling;
        }

        return null;
    }

    public function handle_elementor( array &$element, array $element_structure ): void {
        if ( empty( $element['widgetType'] ) ) {
            return;
        }

        if ( $element['widgetType'] !== 'button' ) {
            return;
        }

        $element['settings']['text']        = $element_structure['content'];
        $element['settings']['link']['url'] = $element_structure['link'] ?? home_url( '/' );
    }
}
