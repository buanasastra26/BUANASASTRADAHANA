<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use Hostinger\AiAssistant\Functions;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\AuditSeo;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\BulkUpdateSeo;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\GetSeo;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\UpdateSeo;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class SeoTools {
    private array $tool_classes = array(
        GetSeo::class,
        UpdateSeo::class,
        BulkUpdateSeo::class,
        AuditSeo::class,
    );

    public function register(): void {
        foreach ( $this->tool_classes as $class_name ) {
            try {
                $tool = new $class_name();
                $tool->register();
            } catch ( Throwable $e ) {
                Functions::log_event( "Failed to register SEO tool {$class_name}: " . $e->getMessage() );
            }
        }
    }
}
