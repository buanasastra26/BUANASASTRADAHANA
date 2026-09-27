<?php

namespace Hostinger\AiTheme\GutenbergBlocks\AiContent;

class AiContent {
    public function __construct() {
        add_action( 'init', [ $this, 'register_block' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_assets_for_editor' ] );
    }

    public function register_block() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type( __DIR__ . '/block.json', [
            'render_callback' => [ $this, 'render_block' ],
        ] );
    }

    public function enqueue_assets_for_editor(): void {
        if ( ! is_admin() ) {
            return;
        }

        wp_register_script(
            'hostinger-ai-content-block-editor-script',
            get_template_directory_uri() . '/gutenberg-blocks/AiContent/build/index.js',
            [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-i18n' ],
            wp_get_theme()->get( 'Version' ),
            true
        );

        wp_enqueue_script( 'hostinger-ai-content-block-editor-script' );

        wp_add_inline_script(
            'hostinger-ai-content-block-editor-script',
            'window.hst_ai_block_data = ' . wp_json_encode([
                'user_id' => get_current_user_id(),
            ]) . ';',
            'before'
        );
    }

    public function render_block( $attributes, $content, $block ) {
        ob_start();
        require __DIR__ . '/render.php';

        return ob_get_clean();
    }
}