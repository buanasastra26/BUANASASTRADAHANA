<?php

namespace Hostinger\AiAssistant\Mcp\Abilities;

use Hostinger\AiAssistant\Mcp\Abilities\Tools\CategoriesTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\CommentsTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\CustomPostTypesTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\ElementorTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\HostingerPluginTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\LiteSpeedCacheTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\MediaTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\MediaUploadTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\PagesTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\PostsTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\SeoTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\SettingsTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\SiteInfoTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\TagsTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\UsersTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\WooCouponsTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\WooOrdersTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\PluginTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\ThemeTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\WooProductsTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\RevisionsTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\MenusTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\NavigationTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\TemplatePartsTools;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Discovery\DiscoverAbilitiesTool;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Discovery\ExecuteAbilityTool;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Discovery\GetAbilityInfoTool;
use Hostinger\AiAssistant\Mcp\Abilities\Resources\PluginsInfoResource;
use Hostinger\AiAssistant\Mcp\Abilities\Resources\SiteInfoResource;
use Hostinger\AiAssistant\Mcp\Abilities\Resources\SiteSettingsResource;
use Hostinger\AiAssistant\Mcp\Abilities\Resources\ThemeInfoResource;
use Hostinger\AiAssistant\Mcp\Abilities\Resources\UsersInfoResource;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class AbilitiesRegistry {
    public const CATEGORY = 'hostinger-ai-assistant';

    public const DISCOVERY_CATEGORY = 'hostinger-ai-assistant-mcp';

    private array $tools = array(
        PostsTools::class,
        PagesTools::class,
        CategoriesTools::class,
        TagsTools::class,
        UsersTools::class,
        CustomPostTypesTools::class,
        MediaTools::class,
        MediaUploadTools::class,
        SettingsTools::class,
        SiteInfoTools::class,
        WooProductsTools::class,
        WooOrdersTools::class,
        WooCouponsTools::class,
        HostingerPluginTools::class,
        LiteSpeedCacheTools::class,
        ElementorTools::class,
        ThemeTools::class,
        PluginTools::class,
        RevisionsTools::class,
        MenusTools::class,
        NavigationTools::class,
        TemplatePartsTools::class,
        CommentsTools::class,
        SeoTools::class,
    );

    private array $resources = array(
        SiteInfoResource::class,
        SiteSettingsResource::class,
        PluginsInfoResource::class,
        ThemeInfoResource::class,
        UsersInfoResource::class,
    );

    private array $discovery_tools = array(
        DiscoverAbilitiesTool::class,
        GetAbilityInfoTool::class,
        ExecuteAbilityTool::class,
    );

    public function init(): void {
        $this->register_categories();
        add_action( 'wp_abilities_api_init', array( $this, 'register_tools' ) );
        add_action( 'wp_abilities_api_init', array( $this, 'register_resources' ) );
        add_action( 'wp_abilities_api_init', array( $this, 'register_discovery_tools' ) );
    }

    public function register_tools(): void {
        $this->register_abilities( $this->tools );
    }

    public function register_resources(): void {
        $this->register_abilities( $this->resources );
    }

    public function register_discovery_tools(): void {
        $this->register_abilities( $this->discovery_tools );
    }

    public function register_categories(): void {
        add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
    }

    public function register_category(): void {
        wp_register_ability_category(
            self::CATEGORY,
            array(
                'label'       => __( 'Hostinger AI Abilities', 'hostinger-ai-assistant' ),
                'description' => __( 'Abilities that provide a way of interacting with WordPress site.', 'hostinger-ai-assistant' ),
            )
        );

        wp_register_ability_category(
            self::DISCOVERY_CATEGORY,
            array(
                'label'       => __( 'Hostinger AI MCP Discovery', 'hostinger-ai-assistant' ),
                'description' => __( 'Meta-tools that let MCP clients discover, inspect and run the Hostinger AI abilities.', 'hostinger-ai-assistant' ),
            )
        );
    }

    private function register_abilities( array $abilities ): void {
        if ( empty( $abilities ) ) {
            return;
        }

        foreach ( $abilities as $ability ) {
            $item = new $ability();

            if ( method_exists( $item, 'register' ) ) {
                $item->register();
            }
        }
    }
}
