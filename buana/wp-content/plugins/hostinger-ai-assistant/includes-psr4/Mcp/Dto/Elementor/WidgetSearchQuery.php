<?php

namespace Hostinger\AiAssistant\Mcp\Dto\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class WidgetSearchQuery {
    public array $widget_types;
    public int $max_depth;
    public bool $include_settings;
    public string $css_class_filter;

    public function __construct( array $widget_types, int $max_depth = 10, bool $include_settings = true, string $css_class_filter = '' ) {
        $this->widget_types     = $widget_types;
        $this->max_depth        = $max_depth;
        $this->include_settings = $include_settings;
        $this->css_class_filter = $css_class_filter;
    }
}
