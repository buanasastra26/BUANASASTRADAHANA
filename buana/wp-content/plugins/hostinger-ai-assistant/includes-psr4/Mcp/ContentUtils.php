<?php

namespace Hostinger\AiAssistant\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ContentUtils {
    /**
     * Check if content is already in Gutenberg block format.
     */
    public static function is_block_content( string $content ): bool {
        return str_starts_with( trim( $content ), '<!-- wp:' );
    }
}
