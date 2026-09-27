<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\AssignGlobalColor;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\CreateContainer;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\FindWidgets;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\GetActiveKit;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\GetKitById;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\GetPageStructure;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\GetWidgetById;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\ListPages;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\DeleteContainer;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\UpdateContainer;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\EditContainer;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\ListTemplates;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\UpdateKit;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\UpdateWidgetContent;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\UpdateWidgetImage;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\UpdateWidgetLink;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\UpdateWidgetStyles;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor\UpdateGlobalStyles;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ElementorTools {
    private array $tool_classes = array(
        AssignGlobalColor::class,
        FindWidgets::class,
        GetActiveKit::class,
        GetKitById::class,
        GetPageStructure::class,
        GetWidgetById::class,
        ListPages::class,
        CreateContainer::class,
        DeleteContainer::class,
        UpdateContainer::class,
        EditContainer::class,
        ListTemplates::class,
        UpdateWidgetContent::class,
        UpdateWidgetImage::class,
        UpdateWidgetLink::class,
        UpdateWidgetStyles::class,
        UpdateGlobalStyles::class,
    );

    public function register(): void {
        if ( ! $this->is_elementor_active() ) {
            return;
        }

        $this->register_tools();
    }

    private function is_elementor_active(): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( 'elementor/elementor.php' );
    }

    private function register_tools(): void {
        foreach ( $this->tool_classes as $class_name ) {
            try {
                $tool = new $class_name();

                if ( method_exists( $tool, 'register' ) ) {
                    $tool->register();
                }
            } catch ( \Throwable $e ) {
                error_log( "Failed to register Elementor tool {$class_name}: " . $e->getMessage() );
            }
        }
    }
}
