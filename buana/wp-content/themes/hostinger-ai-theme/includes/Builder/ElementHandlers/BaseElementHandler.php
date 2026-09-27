<?php

namespace Hostinger\AiTheme\Builder\ElementHandlers;

defined( 'ABSPATH' ) || exit;

use DOMComment;
use DOMElement;
use DOMNode;

class BaseElementHandler implements ElementHandler {
    protected const HERO_SECTION_TYPES = ['hero', 'hero-video', 'about', 'about-us', 'competitive_edge'];

    protected string $builder_type;
    protected string $section_type = '';
    protected int $element_index = 0;

    protected static array $raw_image_cache = [];

    public function __construct(string $builder_type) {
        $this->builder_type = $builder_type;
    }

    public function set_section_context(string $section_type, int $element_index = 0): void {
        $this->section_type = $section_type;
        $this->element_index = $element_index;
    }

    public function handle_gutenberg(DOMElement &$node, array $element_structure): void {

    }

    public function handle_elementor(array &$element, array $element_structure): void {

    }

    public function pre_resolve_image(array $element_structure): void {

    }

    public static function reset_image_cache(): void {
        self::$raw_image_cache = [];
    }

    protected function resolve_image_content(array $element_structure): string {
        $content = $element_structure['default_content']
                   ?? $element_structure['content']
                      ?? '';

        if (empty($content)) {
            return str_replace(array('-', '_'), ' ', $this->section_type);
        }

        return $content;
    }

    protected function build_image_context(string $content): string {
        $website_description = get_option('hostinger_ai_description', '');
        $image_context       = 'Image for ' . strip_tags($content);

        if (!empty($website_description)) {
            $image_context .= '. Website description: ' . strip_tags($website_description);
        }

        return $image_context;
    }

    protected function prefers_hero_image(): bool {
        return in_array($this->section_type, self::HERO_SECTION_TYPES, true);
    }

    /**
     * Serialized Gutenberg markup separates a block delimiter comment from its element
     * with a newline text node, so the comment is not always the immediate sibling.
     */
    protected function find_preceding_block_comment(DOMElement $node, string $delimiter): ?DOMComment {
        $sibling = $node->previousSibling;

        while ($sibling instanceof DOMNode) {
            if ($sibling instanceof DOMComment
                && str_contains($sibling->nodeValue, $delimiter)
                && !str_contains($sibling->nodeValue, '/' . $delimiter)) {
                return $sibling;
            }

            $sibling = $sibling->previousSibling;
        }

        return null;
    }

    protected function cache_raw_url(string $class, string $index, string $url): void {
        self::$raw_image_cache["{$class}:{$index}"] = $url;
    }

    protected function get_cached_raw_url(string $class, string $index): ?string {
        return self::$raw_image_cache["{$class}:{$index}"] ?? null;
    }
}
