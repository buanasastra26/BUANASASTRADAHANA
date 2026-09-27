<?php

namespace Hostinger\Reach\Blocks;

use Hostinger\Reach\Functions;
use Hostinger\Reach\Setup\Assets;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

abstract class Block {
    public Assets $assets;
    public Functions $functions;
    public string $name;

    public function __construct( Assets $assets, Functions $functions ) {
        $this->assets    = $assets;
        $this->functions = $functions;
    }

    public function get_block_name(): string {
        return "hostinger-reach-$this->name-block";
    }

    public function register(): void {
        register_block_type(
            HOSTINGER_REACH_PLUGIN_DIR . "frontend/blocks/$this->name-block/block.json",
            array(
                'render_callback'     => array( $this, 'render' ),
                'style_handles'       => $this->register_block_style(),
                'view_script_handles' => $this->register_block_script(),
            )
        );
    }

    public function enqueue_block(): void {
        if ( $this->functions->block_file_exists( "$this->name.js" ) === false ) {
            return;
        }

        wp_enqueue_script(
            $this->get_block_name() . '-editor',
            $this->functions->get_blocks_url() . "$this->name.js",
            array( 'react', 'wp-api-fetch', 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-i18n' ),
            filemtime( $this->functions->get_block_file_name( "$this->name.js" ) ),
            true
        );

        wp_set_script_translations( $this->get_block_name(), 'hostinger-reach', HOSTINGER_REACH_PLUGIN_DIR . 'languages' );

        wp_localize_script(
            $this->get_block_name() . '-editor',
            'hostinger_reach_block_editor_data',
            $this->get_block_editor_data()
        );

        $this->autoloader();
    }

    public function register_block_style(): array {
        if ( $this->functions->block_file_exists( "$this->name.css" ) === false ) {
            return array();
        }

        $handle = $this->get_block_name();

        wp_register_style(
            $handle,
            $this->functions->get_blocks_url() . "$this->name.css",
            array(),
            filemtime( $this->functions->get_block_file_name( "$this->name.css" ) )
        );

        return array( $handle );
    }

    public function register_block_script(): array {
        if ( $this->functions->block_file_exists( "$this->name-view.js" ) === false ) {
            return array();
        }

        $handle = $this->get_block_name() . '-view';

        wp_register_script(
            $handle,
            $this->functions->get_blocks_url() . "$this->name-view.js",
            array(),
            filemtime( $this->functions->get_block_file_name( "$this->name-view.js" ) ),
            true
        );

        wp_localize_script(
            $handle,
            "hostinger_reach_{$this->name}_block_data",
            $this->data()
        );

        return array( $handle );
    }

    protected function get_block_editor_data(): array {
        return array(
            'rest_url'         => esc_url_raw( rest_url() ),
            'embed_script_url' => HOSTINGER_REACH_EMBED_SCRIPT_URL,
            'nonce'            => wp_create_nonce( 'wp_rest' ),
        );
    }

    abstract public function autoloader(): void;
    abstract public function data(): array;
    abstract public function render( array $attributes ): bool|string;
}
