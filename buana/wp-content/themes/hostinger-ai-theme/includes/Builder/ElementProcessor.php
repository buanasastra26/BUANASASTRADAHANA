<?php

namespace Hostinger\AiTheme\Builder;

use DOMDocument;
use DOMXPath;
use Hostinger\AiTheme\Builder\ElementHandlers\BackgroundImageHandler;
use Hostinger\AiTheme\Builder\ElementHandlers\BaseElementHandler;
use Hostinger\AiTheme\Builder\ElementHandlers\ButtonHandler;
use Hostinger\AiTheme\Builder\ElementHandlers\CoverImageHandler;
use Hostinger\AiTheme\Builder\ElementHandlers\ImageHandler;
use Hostinger\AiTheme\Builder\ElementHandlers\MaskImageHandler;
use Hostinger\AiTheme\Builder\ElementHandlers\TitleHandler;
use Hostinger\AiTheme\Builder\ElementHandlers\VideoHandler;
use Hostinger\AiTheme\Builder\LinkAssigner;
use Hostinger\AiTheme\Constants\ElementClassConstant;

defined( 'ABSPATH' ) || exit;

class ElementProcessor {
    /**
     * @var array
     */
    protected array $handlers = [];

    /**
     * @var string
     */
    private array $section;

    /**
     * @var Helper
     */
    private Helper $helper;

    private string $builder_type;

    /**
     * @param array $section
     */
    public function __construct( array $section ) {
        $this->builder_type = get_option( 'hostinger_ai_builder_type', 'gutenberg' );

        $handler_types = array(
            'title' => new TitleHandler( $this->builder_type ),
            'button' => new ButtonHandler( $this->builder_type ),
            'background-image' => new BackgroundImageHandler( $this->builder_type ),
            'image' => new ImageHandler( $this->builder_type ),
            'mask-image' => new MaskImageHandler( $this->builder_type ),
            'cover-image' => new CoverImageHandler( $this->builder_type ),
            'video' => new VideoHandler( $this->builder_type ),
        );

        $handlers_classes = [
            ElementClassConstant::TITLE               => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::SUBTITLE            => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::CTA_BUTTON          => ElementClassConstant::BUTTON_HANDLER,
            ElementClassConstant::PROJECT_TITLE       => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::SERVICE_TITLE       => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::TESTIMONIAL_TEXT    => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::SERVICE_DESCRIPTION => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::PROJECT_DESCRIPTION => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::DESCRIPTION         => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::TESTIMONIAL_IMAGE   => ElementClassConstant::IMAGE_HANDLER,
            ElementClassConstant::IMAGE               => ElementClassConstant::IMAGE_HANDLER,
            ElementClassConstant::MASK_IMAGE          => ElementClassConstant::MASK_IMAGE_HANDLER,
            ElementClassConstant::SERVICE_IMAGE       => ElementClassConstant::IMAGE_HANDLER,
            ElementClassConstant::PROJECT_IMAGE       => ElementClassConstant::IMAGE_HANDLER,
            ElementClassConstant::BACKGROUND_IMAGE    => ElementClassConstant::BACKGROUND_IMAGE_HANDLER,
            ElementClassConstant::CARD_TITLE          => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::CARD_DESCRIPTION    => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::CARD_PRICE          => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::WORKPLACE           => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::DATE                => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::COVER_IMAGE         => ElementClassConstant::COVER_IMAGE_HANDLER,
            ElementClassConstant::QUESTION            => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::ANSWER              => ElementClassConstant::TITLE_HANDLER,
            ElementClassConstant::BACKGROUND_VIDEO    => ElementClassConstant::VIDEO_HANDLER,
        ];

        foreach($handlers_classes as $handler_class => $handler_type) {
            $this->handlers[$handler_class] = $handler_types[$handler_type];
        }

        $this->section = $section;
    }

    /**
     * @param Helper $helper
     *
     * @return void
     */
    public function setHelper( Helper $helper ): void {
        $this->helper = $helper;
    }

    /**
     * @param DOMDocument $dom
     *
     * @return mixed
     */
    public function process( DOMDocument $dom ): string {
        $xpath = new DOMXPath($dom);
        $text_nodes = $xpath->query('//*[contains(@class,"' . ElementClassConstant::PREFIX . '")]');

        $section_type  = $this->section['type'] ?? $this->section['section'] ?? '';
        $link_assigner = new LinkAssigner( $section_type, $this->section['elements'] );

        foreach ($this->handlers as $handler) {
            $handler->set_section_context($section_type, 0);
        }

        foreach ($text_nodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $classes = $node->getAttribute('class');

                if (empty($classes)) {
                    continue;
                }

                preg_match_all(ElementClassConstant::PATTERN, $classes, $matches);
                $ai_elements = $matches[0];

                $index = $this->helper->extract_index_number($classes);

                foreach ($ai_elements as $ai_element) {
                    if (isset($this->handlers[$ai_element])) {
                        $element_data = [
                            'class' => $ai_element,
                            'index' => $index
                        ];

                        $element_structure = $this->helper->find_structure($this->section['elements'], $element_data);

                        if (!empty($element_structure)) {
                            if ( $ai_element === ElementClassConstant::CTA_BUTTON ) {
                                $element_structure['link'] = $link_assigner->take_for_index( (int) $index );
                            }
                            $this->handlers[$ai_element]->set_section_context($section_type, $index);
                            $this->handlers[$ai_element]->handle_gutenberg($node, $element_structure);
                        }
                    }
                }
            }
        }

        $html = $dom->saveHTML();

        $html = preg_replace('/<\/html>$/', '', $html);
        $html = preg_replace('/<\/body>$/', '', $html);

        return $html;
    }

    public function prepare_json(): array {
        $json_data = json_decode( $this->section['html'] ?? '', true );

        $section_type = $this->section['type'] ?? $this->section['section'] ?? '';

        foreach ($this->handlers as $handler) {
            $handler->set_section_context($section_type, 0);
        }

        $this->pre_resolve_images();

        $link_assigner = new LinkAssigner( $section_type, $this->section['elements'] );

        $processed_data = $this->traverse_elementor_data($json_data, function ($element) use ($section_type, $link_assigner) {
            if ( isset( $element['widgetType'] ) && $element['widgetType'] === 'hostinger-reach' ) {
                $element['settings']['formId'] = HostingerReachBuilder::FORM_ID;
            }

            $css_classes = $this->get_element_css_classes( $element );

            if (!empty($css_classes)) {
                $ai_elements = $this->helper->extract_class_names($css_classes, ElementClassConstant::PATTERN);

                if(!empty($ai_elements)) {
                    $element_index = $this->helper->extract_index_number( $css_classes );

                    foreach ( $ai_elements as $ai_element ) {
                        $element_data = [
                            'class' => $ai_element,
                            'index' => $element_index,
                        ];

                        $element_structure = $this->helper->find_structure($this->section['elements'], $element_data);
                        if (!empty($element_structure)) {
                            if ( $ai_element === ElementClassConstant::CTA_BUTTON ) {
                                $element_structure['link'] = $link_assigner->take_for_index( (int) $element_index );
                            }
                            $this->handlers[$ai_element]->set_section_context($section_type, $element_index);
                            $this->handlers[$ai_element]->handle_elementor($element, $element_structure);
                        }
                    }
                }
            }

            return $element;
        });

        return $processed_data;
    }

    private function pre_resolve_images(): void {
        BaseElementHandler::reset_image_cache();

        foreach ($this->section['elements'] as $element_structure) {
            if (isset($element_structure['source_from'])) {
                continue;
            }

            $class = $element_structure['class'] ?? '';
            if (isset($this->handlers[$class])) {
                $this->handlers[$class]->pre_resolve_image($element_structure);
            }
        }
    }

    private function traverse_elementor_data(array $data, callable $callback): array {
        foreach ($data as $key => $element) {
            if (is_array($element) && isset($element['elType'])) {
                $data[$key] = $callback($element);

                if (isset($data[$key]['elements']) && is_array($data[$key]['elements'])) {
                    $data[$key]['elements'] = $this->traverse_elementor_data($data[$key]['elements'], $callback);
                }
            } elseif (is_array($element)) {
                $data[$key] = $this->traverse_elementor_data($element, $callback);
            }
        }

        return $data;
    }

    private function get_element_css_classes( array $element ): string {
        $css_classes = $element['settings']['css_classes'] ?? '';
        if ( empty( $css_classes ) ) {
            $css_classes = $element['settings']['_css_classes'] ?? '';
        }

        return $css_classes;
    }
}
