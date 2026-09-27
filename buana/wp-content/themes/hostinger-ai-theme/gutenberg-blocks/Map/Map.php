<?php

namespace Hostinger\AiTheme\GutenbergBlocks\Map;

class Map {
    public function __construct() {
        add_action( 'init', [ $this, 'register_block' ] );
    }

    public function register_block(): void {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type( __DIR__ . '/block.json', [
            'render_callback' => [ $this, 'render_block' ],
        ] );
    }

    public function render_block( array $attributes, string $content, $block ): string {
        ob_start();
        require __DIR__ . '/render.php';

        return (string) ob_get_clean();
    }
}
