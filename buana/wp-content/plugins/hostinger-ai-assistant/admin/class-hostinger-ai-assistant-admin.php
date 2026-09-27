<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://hostinger.com
 * @since      1.0.0
 *
 * @package    Hostinger_Ai_Assistant
 * @subpackage Hostinger_Ai_Assistant/admin
 */

use Hostinger\WpMenuManager\Menus;
use Hostinger\WpHelper\Utils;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Hostinger_Ai_Assistant
 * @subpackage Hostinger_Ai_Assistant/admin
 * @author     Hostinger <info@hostinger.com>
 */
class Hostinger_Ai_Assistant_Admin {
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $plugin_name The ID of this plugin.
     */
    private string $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private string $version;
    private array $module_scripts = array();

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version The version of this plugin.
     *
     * @since    1.0.0
     */
    public function __construct( string $plugin_name, string $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;

        add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_scripts' ), 10, 3 );
    }

    public function add_module_type_to_scripts( string $tag, string $handle, string $src ): string {
        if ( in_array( $handle, $this->module_scripts, true ) ) {
            $tag = str_replace( '<script ', '<script type="module" ', $tag );
        }

        return $tag;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles(): void {
        if ( $this->is_hostinger_menu_page() ) {
            wp_enqueue_style( $this->plugin_name, HOSTINGER_AI_ASSISTANT_ASSETS_URL . '/css/hostinger-ai-assistant-admin.min.css', array(), $this->version, 'all' );
        }

        if ( class_exists( 'WooCommerce' ) ) {
            wp_enqueue_style( 'hostinger_ai_assistant_woo_styles', HOSTINGER_AI_ASSISTANT_ASSETS_URL . '/css/hostinger-woo-requests.min.css', array(), $this->version, 'all' );
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts(): void {
        $translations  = new Hostinger_Frontend_Translations();
        $global_params = array_merge(
            $translations->get_frontend_translations(),
            array(
                'tabUrl' => admin_url() . 'admin.php?page=hostinger-ai-assistant',
            )
        );

        if ( $this->is_hostinger_menu_page() ) {
            if ( class_exists( 'WooCommerce' ) ) {
                wp_dequeue_script( 'select2' );
                wp_deregister_script( 'select2' );
            }

            wp_register_script(
                'select2',
                HOSTINGER_AI_ASSISTANT_ASSETS_URL . '/js/vendor/select2.full.min.js',
                array( 'jquery' ),
                '4.1.0',
                array( 'in_footer' => false )
            );

            wp_enqueue_script(
                $this->plugin_name,
                HOSTINGER_AI_ASSISTANT_ASSETS_URL . '/js/hostinger-ai-assistant-admin.min.js',
                array(
                    'jquery',
                    'select2',
                    'wp-i18n',
                ),
                $this->version,
                array( 'in_footer' => false )
            );

            wp_localize_script( $this->plugin_name, 'hostingerAiAssistant', $global_params );
        }

        if ( class_exists( 'WooCommerce' ) ) {
            wp_enqueue_script(
                'hostinger_ai_assistant_woo_requests',
                HOSTINGER_AI_ASSISTANT_ASSETS_URL . '/js/hostinger-woo-requests.min.js',
                array(
                    'jquery',
                    'wp-i18n',
                ),
                $this->version,
                array( 'in_footer' => false )
            );
            $this->module_scripts[] = 'hostinger_ai_assistant_woo_requests';
        }

        $this->enqueue_chatbot();
    }

    public function enqueue_custom_editor_assets(): void {
        $translations  = new Hostinger_Frontend_Translations();
        $global_params = array_merge(
            $translations->get_frontend_translations(),
            array(
                'tabUrl' => admin_url() . 'admin.php?page=hostinger-ai-assistant',
            )
        );

        wp_enqueue_script(
            'custom-link-in-toolbar',
            HOSTINGER_AI_ASSISTANT_ASSETS_URL . '/js/hostinger-buttons.js',
            array(
                'jquery',
                'wp-blocks',
                'wp-dom',
                'wp-i18n',
            ),
            $this->version,
            false
        );
        wp_set_script_translations( 'custom-link-in-toolbar', 'hostinger-ai-assistant' );
        wp_localize_script( 'custom-link-in-toolbar', 'hostingerAiAssistant', $global_params );
    }

    public function add_ai_assistant_menu_item( $submenus ): array {
        $submenus[] = array(
            'page_title' => __( 'AI Content Creator', 'hostinger-ai-assistant' ),
            'menu_title' => __( 'AI Content Creator', 'hostinger-ai-assistant' ),
            'capability' => 'publish_posts',
            'menu_slug'  => 'hostinger-ai-assistant',
            'callback'   => array( $this, 'create_ai_assistant_tab_view' ),
            'menu_order' => 10,
        );

        return $submenus;
    }

    public function add_admin_bar_item( array $menu_items ): array {
        if ( ! current_user_can( 'publish_posts' ) ) {
            return $menu_items;
        }

        $menu_items[] = array(
            'id'    => 'hostinger-ai-assistant-ai-content-creator',
            'title' => esc_html__( 'AI Content Creator', 'hostinger-ai-assistant' ),
            'href'  => admin_url( 'admin.php?page=hostinger-ai-assistant' ),
        );

        return $menu_items;
    }

    /**
     * Add AI Assistant view
     *
     * @since    1.0.0
     */
    public function create_ai_assistant_tab_view(): void {
        echo Menus::renderMenuNavigation();
        include_once HOSTINGER_AI_ASSISTANT_ABSPATH . 'admin/partials/hostinger-ai-assistant-tab-view.php';
    }

    public function enqueue_chatbot(): void {
        if ( empty( Utils::getApiToken() ) ) {
            return;
        }

        $translations = new Hostinger_Frontend_Translations();

        wp_enqueue_style(
            'hostinger_chatbot_vendor',
            HOSTINGER_AI_ASSISTANT_ASSETS_URL . '/css/chatbot-widget-vendor.min.css',
            array(),
            $this->version,
            'all'
        );

        wp_enqueue_style(
            'hostinger_chatbot',
            HOSTINGER_AI_ASSISTANT_ASSETS_URL . '/css/hostinger-chatbot.min.css',
            array( 'hostinger_chatbot_vendor' ),
            $this->version,
            'all'
        );

        wp_enqueue_script(
            'hostinger_chatbot',
            HOSTINGER_AI_ASSISTANT_ASSETS_URL . '/js/hostinger-chatbot.min.js',
            array(
                'jquery',
                'wp-i18n',
            ),
            $this->version,
            array( 'strategy' => 'defer' )
        );
        $this->module_scripts[] = 'hostinger_chatbot';

        $user   = wp_get_current_user();
        $locale = get_user_locale();

        wp_localize_script(
            'hostinger_chatbot',
            'hostingerChatbot',
            array_merge(
                $translations->get_chatbot_translations(),
                array(
                    'nonce'       => wp_create_nonce( 'wp_rest' ),
                    'chatbot_uri' => esc_url_raw( rest_url() ),
                    'ajax_nonce'  => wp_create_nonce( 'updates' ),
                    'admin_url'   => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
                    'user_id'     => ! empty( $user->ID ) ? $user->ID : 0,
                    'language'    => $locale,
                )
            )
        );
    }

    public function enqueue_chatbot_on_frontend(): void {
        if ( ! Hostinger_Ai_Assistant_Helper::should_load_frontend_chatbot() ) {
            return;
        }

        $this->enqueue_chatbot();
    }

    /**
     * @return bool
     */
    private function is_hostinger_menu_page(): bool {
        $admin_path = parse_url( admin_url(), PHP_URL_PATH );

        $pages = array(
            $admin_path . 'admin.php?page=' . Menus::MENU_SLUG,
        );

        $pages[] = $admin_path . 'admin.php?page=hostinger-ai-assistant';

        $utils = new Utils();

        foreach ( $pages as $page ) {
            if ( $utils->isThisPage( $page ) ) {
                return true;
            }
        }

        return false;
    }
}
