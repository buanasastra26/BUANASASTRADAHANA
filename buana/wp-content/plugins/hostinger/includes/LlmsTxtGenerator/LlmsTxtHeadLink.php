<?php

namespace Hostinger\LlmsTxtGenerator;

use Hostinger\Admin\PluginSettings;

defined( 'ABSPATH' ) || exit;

class LlmsTxtHeadLink {

    protected PluginSettings $plugin_settings;
    protected LlmsTxtFileHelper $file_helper;

    public function __construct( PluginSettings $plugin_settings, LlmsTxtFileHelper $llmstxt_file_helper ) {
        $this->plugin_settings = $plugin_settings;
        $this->file_helper     = $llmstxt_file_helper;
    }

    public function render(): void {
        $settings = $this->plugin_settings->get_plugin_settings();

        if ( ! $settings->get_enable_llms_txt() ) {
            return;
        }

        if ( ! $this->file_helper->llmstxt_file_exists() ) {
            return;
        }

        printf( '<link rel="describedby" href="%s" />' . "\n", esc_url( $this->file_helper->get_llmstxt_file_url() ) );
    }
}
