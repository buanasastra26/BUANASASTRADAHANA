<?php

namespace Hostinger\AiTheme\Builder\ElementHandlers;

defined( 'ABSPATH' ) || exit;

use DOMElement;

interface ElementHandler {
    public function handle_gutenberg(DOMElement &$node, array $element_structure);
    public function handle_elementor(array &$element, array $element_structure);
}
