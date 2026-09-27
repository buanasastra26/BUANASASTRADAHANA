<?php

namespace Hostinger\AiTheme\Builder\ElementHandlers;

defined( 'ABSPATH' ) || exit;

class MaskImageHandler extends BaseElementHandler {
    private const ASSETS_BLOCKS_DIR = 'assets/images/blocks/';

    public function handle_elementor( array &$element, array $element_structure ): void {
        if ( empty( $element['settings'] ) || empty( $element_structure['mask_image'] ) ) {
            return;
        }

        $filename = $element_structure['mask_image'];
        $url      = get_template_directory_uri() . '/' . self::ASSETS_BLOCKS_DIR . $filename;

        if ( isset( $element['settings']['_mask_image'] ) ) {
            $element['settings']['_mask_image']['url'] = $url;
            $element['settings']['_mask_image']['id']  = 0;
        }
    }
}
