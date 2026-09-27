<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Constants\BuilderType;

defined( 'ABSPATH' ) || exit;

class ElementorBuilder extends AbstractPluginBuilder {
    private const PLUGIN_FILE = 'elementor/elementor.php';
    private const PLUGIN_NAME = 'Elementor';
    private const DOWNLOAD_URL = 'https://downloads.wordpress.org/plugin/elementor.zip';

    protected function get_plugin_file(): string {
        return self::PLUGIN_FILE;
    }

    protected function get_plugin_name(): string {
        return self::PLUGIN_NAME;
    }

    protected function get_download_url(): string {
        return self::DOWNLOAD_URL;
    }

    protected function get_error_code(): string {
        return 'elementor';
    }

    protected function is_enabled(): bool {
        return get_option( 'hostinger_ai_builder_type', BuilderType::GUTENBERG ) === BuilderType::ELEMENTOR;
    }

    protected function after_installation(): void {
        update_option( 'elementor_onboarded', 1 );
    }
}

