<?php

namespace Hostinger\AiTheme\Admin;

use Hostinger\AiTheme\Data\HostingerEcommerceHelper;
use Hostinger\AiTheme\Data\NewEngineExperiment;

defined( 'ABSPATH' ) || exit;

class Menu {
    public const AI_BUILDER_MENU_SLUG    = 'hostinger-ai-website-creation';
    public const AI_BUILDER_V2_MENU_SLUG = 'hostinger-ai-website-creation-v2';

    public function __construct() {
        add_filter( 'hostinger_menu_subpages', array( $this, 'add_menu_sub_pages' ), 999 );
    }

    /**
     * @param array $submenus
     *
     * @return array
     */
    public function add_menu_sub_pages( array $submenus ): array {
        $submenus[] = array(
            'page_title'      => __( 'AI Website Builder', 'hostinger-ai-theme' ),
            'menu_title'      => __( 'AI Website Builder', 'hostinger-ai-theme' ),
            'capability'      => 'manage_options',
            'menu_slug'       => NewEngineExperiment::is_enabled() || HostingerEcommerceHelper::is_active() ?  self::AI_BUILDER_V2_MENU_SLUG : self::AI_BUILDER_MENU_SLUG,
            'callback'        => array( $this, 'renderWebsiteCreation' ),
            'menu_identifier' => 'generic',
            'menu_order'      => 100,
        );

        return $submenus;
    }

    /**
     * @return void
     */
    public function renderWebsiteCreation(): void {
        include_once __DIR__ . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'WebsiteCreationOnboarding.php';
    }
}
