<?php

namespace Hostinger\AiTheme\Builder\ElementHandlers;

use Hostinger\AiTheme\Builder\ImageManager;
use Hostinger\AiTheme\Constants\ElementClassConstant;
use DOMComment;
use DOMElement;

defined( 'ABSPATH' ) || exit;

class CoverImageHandler extends BaseElementHandler {

    public function handle_gutenberg(DOMElement &$node, array $element_structure): void {
        $content = $this->resolve_image_content($element_structure);

        if ( empty( $content ) ) {
            return;
        }

        $previousElement = $this->find_preceding_block_comment($node, 'wp:cover');

        if ( ! $previousElement instanceof DOMComment ) {
            return;
        }

        $value = str_replace(' wp:cover ', '', $previousElement->nodeValue);
        $block = json_decode($value, true);

        $image_manager = new ImageManager( $content );
        $image_data = $image_manager->get_image_data($this->prefers_hero_image());

        $images = $node->getElementsByTagName('img');

        if ( property_exists( $image_data, 'image' ) && $image_data->image !== '' && $images->length > 0 ) {
            $image_url = $image_manager->modify_image_url( $image_data->image, $element_structure );
			$alt_description = $image_data->alt_description ?? ( $element_structure['content'] ?? '' );

            if ( ! empty($block['className'])
                 && str_contains(
                     $block['className'],
                     ElementClassConstant::COVER_IMAGE
                 )) {
                $block['url'] = $image_url;
                $block['alt'] = $alt_description;
            }

            $img = $images->item(0);
            $img->setAttribute('src', $image_url);
            $img->setAttribute('alt', $alt_description);

            $previousElement->nodeValue = ' wp:cover ' . json_encode( $block ) .' ';
        }
    }

    public function handle_elementor(array &$element, array $element_structure): void {
        $css_classes = $element['settings']['css_classes'] ?? '';
        if ( ! str_contains( $css_classes, ElementClassConstant::COVER_IMAGE ) ) {
            return;
        }

        $content = $this->resolve_image_content($element_structure);

        if (empty($content)) {
            return;
        }

        $image_manager = new ImageManager($this->build_image_context($content));
        $image_data = $image_manager->get_image_data($this->prefers_hero_image());

        if (empty($image_data) || !property_exists($image_data, 'image') || $image_data->image === '') {
            return;
        }

        $image_url = $image_manager->modify_image_url($image_data->image, $element_structure);

        $element['settings']['background_background'] = 'classic';
        $element['settings']['background_image'] = [
            'url' => $image_url,
            'id' => '',
            'source' => 'url'
        ];
    }
}
