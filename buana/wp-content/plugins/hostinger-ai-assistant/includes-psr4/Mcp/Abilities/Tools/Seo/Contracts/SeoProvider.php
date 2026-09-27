<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Contracts;

use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\SeoData;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

interface SeoProvider {
    public function get_key(): string;

    public function get_label(): string;

    public function is_active(): bool;

    /**
     * @return string[]
     */
    public function supported_fields(): array;

    public function read( int $post_id ): SeoData;

    /**
     * @return array{written: string[], skipped: string[]}
     */
    public function write( int $post_id, SeoData $data ): array;
}
