<?php

namespace Hostinger\AiTheme;

use Hostinger\AiTheme\GutenbergBlocks\Booking\Booking;
use Hostinger\AiTheme\GutenbergBlocks\AiContent\AiContent;
use Hostinger\AiTheme\GutenbergBlocks\ContactForm\ContactForm;
use Hostinger\AiTheme\GutenbergBlocks\Map\Map;

class GutenbergBlocks {
    /**
     * Array of block classes to initialize
     *
     * @var array
     */
    private $blocks = [
        'BookingBlock' => Booking::class,
        'AiContent' => AiContent::class,
        'ContactForm' => ContactForm::class,
        'Map' => Map::class,
        // Add more blocks here in future
    ];

    /**
     * Initialize the blocks
     */
    public function __construct() {
        $this->init_blocks();
    }

    /**
     * Initialize all registered blocks
     *
     * @return void
     */
    private function init_blocks(): void {
        foreach ( $this->blocks as $block_name => $block_class ) {
            if ( class_exists( $block_class ) ) {
                new $block_class();
            }
        }
    }

    /**
     * Register a new block
     *
     * @param string $name  Block name
     * @param string $class Block class name
     *
     * @return void
     */
    public function register_block( string $name, string $class ): void {
        $this->blocks[$name] = $class;
    }
}