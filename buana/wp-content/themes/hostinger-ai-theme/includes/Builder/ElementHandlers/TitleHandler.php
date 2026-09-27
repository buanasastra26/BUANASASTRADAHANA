<?php

namespace Hostinger\AiTheme\Builder\ElementHandlers;

use DOMElement;

defined( 'ABSPATH' ) || exit;

class TitleHandler extends BaseElementHandler {
    public function handle_gutenberg(DOMElement &$node, array $element_structure): void {
        $prefix = ! empty( $element_structure['prefix'] ) ? $element_structure['prefix'] : '';
        $suffix = ! empty( $element_structure['suffix'] ) ? $element_structure['suffix'] : '';

        $node->nodeValue = $prefix . htmlspecialchars( $element_structure['content'] ) . $suffix;
    }

    public function handle_elementor(array &$element, array $element_structure): void {
        if(empty($element['settings'])) {
            return;
        }

        if($element['widgetType'] === 'heading') {
            $element['settings']['title'] = $element_structure['content'];
        }

        if($element['widgetType'] === 'text-editor') {
            $element['settings']['editor'] = $element_structure['content'];
        }
    }
}
