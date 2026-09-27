<?php

namespace Hostinger\AiTheme\Builder\ElementHandlers;

use Hostinger\AiTheme\Builder\ImageManager;
use DOMComment;
use DOMElement;

defined( 'ABSPATH' ) || exit;

class BackgroundImageHandler extends BaseElementHandler {

    public function handle_gutenberg(DOMElement &$node, array $element_structure): void {
        $previousElement = $this->find_preceding_block_comment($node, 'wp:group');

        if (!$previousElement instanceof DOMComment) {
            return;
        }

        $value = str_replace(' wp:group ', '', $previousElement->nodeValue);
        $block = json_decode($value, true);

        if (empty($block)) {
            return;
        }

        $image_url = $this->get_image_url($element_structure);
        if ($image_url && !empty($block['className']) && str_contains($block['className'], 'hostinger-ai-background-image')) {
            $block['style']['background']['backgroundImage'] = [
                'url'   => $image_url,
                'id'    => 0,
                'title' => ''
            ];
            $previousElement->nodeValue = ' wp:group ' . json_encode($block) . ' ';
        }
    }

    public function handle_elementor(array &$element, array $element_structure): void {
        $image_url = $this->resolve_image_url($element_structure);
        if (!empty($image_url)) {
            if ( ! empty( $element['settings']['background_image'] ) ) {
                $element['settings']['background_image'] = [
                    'url' => $image_url,
                    'source' => 'url'
                ];
            }

            if ( ! empty( $element['settings']['background_image_mobile'] ) ) {
                $element['settings']['background_image_mobile'] = [
                    'url' => $image_url,
                    'source' => 'url'
                ];
            }
        }
    }

    public function pre_resolve_image(array $element_structure): void {
        $content = $this->resolve_image_content($element_structure);

        if (empty($content)) {
            return;
        }

        $image_manager = new ImageManager($content);
        $image_data = $image_manager->get_image_data($this->prefers_hero_image());

        if (!empty($image_data->image)) {
            $this->cache_raw_url(
                $element_structure['class'],
                $element_structure['index'],
                $image_data->image
            );
        }
    }

    private function resolve_image_url(array $element_structure): ?string {
        if (isset($element_structure['source_from'])) {
            $src = $element_structure['source_from'];
            $raw = $this->get_cached_raw_url($src['class'], $src['index']);
            if ($raw) {
                return (new ImageManager(''))->modify_image_url($raw, $element_structure);
            }
            return null;
        }

        $raw = $this->get_cached_raw_url($element_structure['class'], $element_structure['index']);
        if ($raw) {
            return (new ImageManager(''))->modify_image_url($raw, $element_structure);
        }

        return $this->get_image_url($element_structure);
    }

    private function get_image_url(array $element_structure): ?string {
        $content = $this->resolve_image_content($element_structure);

        if (empty($content)) {
            return null;
        }

        $image_manager = new ImageManager($content);
        $image_data = $image_manager->get_image_data($this->prefers_hero_image());

        if (!empty($image_data->image)) {
            return $image_manager->modify_image_url($image_data->image, $element_structure);
        }

        return null;
    }
}
