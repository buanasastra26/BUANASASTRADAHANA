<?php

namespace Hostinger\AiTheme\Builder;

use DOMDocument;
use DOMXPath;
use Exception as ExceptionAlias;
use Hostinger\AiTheme\Constants\BuilderType;

defined( 'ABSPATH' ) || exit;

class SectionBuilder {
    /**
     * Sections that only exist for one builder. When the active builder has no
     * variations for the key, the value is built instead, so the page gets a
     * usable section rather than a silent gap.
     */
    private const SECTION_FALLBACKS = array(
        'hero-portfolio' => 'hero',
        'hero-services'  => 'hero',
        'hero-india'     => 'hero',
    );

    /**
     * @var string
     */
    private string $section;

    /**
     * @var string
     */
    private string $block_content;

    /**
     * @var array
     */
    private array $block_structure = array();

    /**
     * @var array
     */
    private array $block_used_elements = array();

    /**
     * @var Helper
     */
    private Helper $helper;

    private string $type;

    /**
     * Absolute path to a specific variation file to load instead of a random one.
     *
     * @var string|null
     */
    private ?string $pinned_variation = null;

    /**
     * @param string $section
     * @param string|null $builder_type Specifies which builder type to use (gutenberg or elementor), overriding the default option value if provided
     */
    public function __construct( string $section, ?string $builder_type = null ) {

        // Use same CTA block for reservation.
        if ( $section == 'reservation' ) {
            $section = 'call-to-action';
        }

        // Reuse the projects block to render an event agenda / schedule.
        if ( $section == 'agenda' ) {
            $section = 'projects';
        }

        $this->section = strtolower($section);
        $this->type = $builder_type ?? get_option('hostinger_ai_builder_type', BuilderType::GUTENBERG);
    }

    /**
     * @param Helper $helper
     *
     * @return void
     */
    public function setHelper( Helper $helper ) {
        $this->helper = $helper;
    }

    /**
     * Pin a specific variation file so pick_section() loads it instead of a random one.
     *
     * @param string $variation_path Absolute path to the variation JSON file.
     *
     * @return void
     */
    public function set_variation( string $variation_path ): void {
        $this->pinned_variation = $variation_path;
    }

    /**
     * @return bool
     * @throws ExceptionAlias
     */
    public function generate(): bool {
        // Random section variation is picked.
        $section_picked = $this->pick_section();

        if(!empty($section_picked)) {
            // Main block structure is loaded.
            $this->load_block_structure();

            // Picked section is scanned, only used elements from block structure are loaded.
            $this->scan_block_elements();

            return true;
        }

        return false;
    }

    /**
     * @return void
     */
    public function pick_section(): bool {
        if ( ! empty( $this->pinned_variation ) ) {
            if ( ! file_exists( $this->pinned_variation ) ) {
                return false;
            }
            $block_file = $this->pinned_variation;
        } else {
            $block_variations = $this->find_variations( $this->section );

            if(empty($block_variations)) {
                $fallback = self::SECTION_FALLBACKS[ $this->section ] ?? '';

                if ( empty( $fallback ) ) {
                    return false;
                }

                $block_variations = $this->find_variations( $fallback );

                if ( empty( $block_variations ) ) {
                    return false;
                }

                $this->section = $fallback;
            }

            shuffle($block_variations);
            $block_file = array_shift($block_variations);
        }

        switch( $this->type ) {
            case BuilderType::GUTENBERG:
                $block_json = json_decode( file_get_contents($block_file), true );
                $this->block_content = $block_json['content'];
                break;
            case BuilderType::ELEMENTOR:
                $this->block_content = file_get_contents($block_file);
                break;
        }

        return true;
    }

    private function find_variations( string $section ): array {
        $variations = glob(get_stylesheet_directory() . DIRECTORY_SEPARATOR . 'blocks' . DIRECTORY_SEPARATOR  . $this->type . DIRECTORY_SEPARATOR  . $section . '-[0-9]*.json');

        return is_array( $variations ) ? $variations : array();
    }

    /**
     * @throws ExceptionAlias
     */
    public function load_block_structure(): void {
        $structure_file = get_stylesheet_directory() . DIRECTORY_SEPARATOR . 'blocks' . DIRECTORY_SEPARATOR  . $this->type . DIRECTORY_SEPARATOR . $this->section . '.json';

        if( !file_exists( $structure_file ) ) {
            throw new ExceptionAlias('Section ' . $this->section . ' structure file does not exists.');
        }

        $structure = file_get_contents( $structure_file );

        if( !empty( $structure) ) {
            $this->block_structure = json_decode($structure, true);
        }
    }

    /**
     * @return void
     */
    public function scan_block_elements(): void {
        if(empty($this->block_structure)) {
            return;
        }

        $elements = $this->extract_elements();
        foreach ($elements as $element_data) {
            $structure = $this->helper->find_structure($this->block_structure, $element_data);

            if(!empty($structure)) {
                $this->block_used_elements[uniqid()] = $structure;
            }
        }
    }

    /**
     * @return string
     */
    public function extract_elements(): array {
        switch( $this->type ) {
            default:
            case 'gutenberg':
                $result = $this->extract_gutenberg_elements();
                break;
            case 'elementor':
                $result = $this->extract_elementor_elements();
                break;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function get_block_used_elements(): array {
        return $this->block_used_elements;
    }

    /**
     * @return string
     */
    public function get_block_content(): string {
        return $this->block_content;
    }

    public function get_section(): string {
        return $this->section;
    }

    private function extract_gutenberg_elements(): array {
        $result = array();

        $dom = new DOMDocument();
        @$dom->loadHTML($this->block_content, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);

        $xpath      = new DOMXPath($dom);
        $pattern    = '/hostinger-ai-[^\s]+/';
        $text_nodes = $xpath->query('//*[contains(@class,"hostinger-ai-")]');
        foreach ($text_nodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $classes = $node->getAttribute('class');

                if(empty($classes)) {
                    continue;
                }

                $ai_elements = $this->helper->extract_class_names($classes, $pattern);

                if(!empty($ai_elements)) {
                    foreach($ai_elements as $ai_element) {
                        $result[] = [
                            'class' => $ai_element,
                            'index' => $this->helper->extract_index_number($classes),
                        ];
                    }
                }
            }
        }

        return $result;
    }

    private function extract_elementor_elements(): array {
        $result = array();

        $json = json_decode( $this->block_content, true );
        if ( empty( $json ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Hostinger AI theme: Something went wrong extracting elementor elements:' );
                error_log( print_r( $this, true ) );
            }

            return $result;
        }

        $this->walk_elementor_elements($json, $result);

        return $result;
    }

    private function walk_elementor_elements(array $elements, array &$results = [], string $path = ''): void {
        foreach ($elements as $index => $element) {

            $css_classes = $element['settings']['css_classes'] ?? '';
            if(empty($css_classes)) {
                $css_classes = $element['settings']['_css_classes'] ?? '';
            }

            if (!empty($css_classes)) {
                $pattern    = '/hostinger-ai-[^\s]+/';
                $ai_elements = $this->helper->extract_class_names($css_classes, $pattern);

                if(!empty($ai_elements)) {
                    foreach ( $ai_elements as $ai_element ) {
                        $results[] = [
                            'class' => $ai_element,
                            'index' => $this->helper->extract_index_number( $css_classes ),
                        ];
                    }
                }
            }

            if (isset($element['elements']) && is_array($element['elements'])) {
                $this->walk_elementor_elements($element['elements'], $results);
            }
        }
    }
}
