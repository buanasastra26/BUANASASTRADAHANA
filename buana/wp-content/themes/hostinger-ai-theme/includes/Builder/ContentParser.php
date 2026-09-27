<?php

namespace Hostinger\AiTheme\Builder;

use DOMDocument;
use Hostinger\AiTheme\Constants\BuilderType;

defined( 'ABSPATH' ) || exit;

class ContentParser {
    /**
     * @var string
     */
    private array $section;
    private string $type;

    /**
     * @param array $content_data
     * @param string|null $builder_type Specifies which builder type to use (gutenberg or elementor), overriding the default option value if provided
     */
    public function __construct( array $section, ?string $builder_type = null ) {
        $this->section = $section;
        $this->type = $builder_type ?? get_option( 'hostinger_ai_builder_type', BuilderType::GUTENBERG );
    }

    /**
     * @return string|array
     */
    public function output() {
        if(empty($this->section['elements'])) {
            return $this->section['html'] ?? '';
        }

        switch( $this->type ) {
            default:
            case BuilderType::GUTENBERG:
                return $this->output_gutenberg();
            case BuilderType::ELEMENTOR:
                return $this->output_elementor();
        }
    }

    private function output_elementor(): array {
        $processor = new ElementProcessor( $this->section );
        $processor->setHelper( new Helper() );

        $json = $processor->prepare_json();

        $translator = new Translator();
        $json = $translator->translate_array( $json );

        return $json;
    }

    private function output_gutenberg(): string {
        $dom = new DOMDocument();
		if ( empty( $this->section['html'] ) ) {
			return '';
		}
        @$dom->loadHTML($this->section['html'], LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);

        $processor = new ElementProcessor( $this->section );
        $processor->setHelper( new Helper() );

        $html = $processor->process( $dom );

        $blocks = parse_blocks( $html );

        $serialized = serialize_blocks( $blocks );

        // URL fix.
        $serialized = str_replace( '\u0026', '&', $serialized );

        // Dash fix.
        $serialized = str_replace( '\u002d', '-', $serialized );

        // Replace trans-* tokens.
        $translator = new Translator();
        $serialized = $translator->translate_string( $serialized );

        return $serialized;
    }
}
