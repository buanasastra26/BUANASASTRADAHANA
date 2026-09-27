<?php

namespace Hostinger\AiTheme\Builder\ElementHandlers;

use Hostinger\AiTheme\Builder\ImageManager;
use DOMElement;

defined( 'ABSPATH' ) || exit;

class ImageHandler extends BaseElementHandler {
    public function handle_gutenberg(DOMElement &$node, array $element_structure): void {
        $image_data = $this->prepare_image_data($element_structure);
        if (empty($image_data)) {
            return;
        }

        $imgs = $node->getElementsByTagName('img');

        if ($imgs->length > 0) {
            $img = $imgs->item(0);
            $img->setAttribute('src', $image_data['url']);
            $img->setAttribute('alt', $image_data['alt']);
        }
    }

    public function handle_elementor(array &$element, array $element_structure): void {
        if (empty($element['settings'])) {
            return;
        }

        $image_data = $this->prepare_image_data($element_structure);
        if (empty($image_data)) {
            return;
        }

        if (isset($element['settings']['image'])) {
            $element['settings']['image']['url'] = $image_data['url'];
            $element['settings']['image']['alt'] = $image_data['alt'];
        }
    }

    public function pre_resolve_image(array $element_structure): void {
        $content = $this->resolve_image_content($element_structure);

        if (empty($content)) {
            return;
        }

        $image_manager = new ImageManager($this->build_image_context($content));
        $image_data = $image_manager->get_image_data($this->prefers_hero_image());

        if (!empty($image_data) && property_exists($image_data, 'image') && $image_data->image !== '') {
            $this->cache_raw_url(
                $element_structure['class'],
                $element_structure['index'],
                $image_data->image
            );
        }
    }

    private function prepare_image_data(array $element_structure): ?array {
        if (isset($element_structure['source_from'])) {
            $src = $element_structure['source_from'];
            $raw = $this->get_cached_raw_url($src['class'], $src['index']);
            if ($raw) {
                $image_url = (new ImageManager(''))->modify_image_url($raw, $element_structure);
                return ['url' => $image_url, 'alt' => ''];
            }
            return null;
        }

        $raw = $this->get_cached_raw_url($element_structure['class'], $element_structure['index']);
        if ($raw) {
            return [
                'url' => (new ImageManager(''))->modify_image_url($raw, $element_structure),
                'alt' => '',
            ];
        }

        $content = $this->resolve_image_content($element_structure);

        if (empty($content)) {
            return null;
        }

        $image_manager = new ImageManager($this->build_image_context($content));
        $image_data = $image_manager->get_image_data($this->prefers_hero_image());

        if (empty($image_data) || !property_exists($image_data, 'image') || $image_data->image === '') {
            return null;
        }

        $image_url       = $image_manager->modify_image_url($image_data->image, $element_structure);
        $alt_description = property_exists($image_data, 'alt_description') && !empty($image_data->alt_description)
            ? $image_data->alt_description
            : '';

        return [
            'url' => $image_url,
            'alt' => $alt_description,
        ];
    }
}
