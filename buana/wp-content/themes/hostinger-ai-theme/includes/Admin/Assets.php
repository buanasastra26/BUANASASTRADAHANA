<?php

namespace Hostinger\AiTheme\Admin;

use Hostinger\AiTheme\Admin\Menu as AdminMenu;
use Hostinger\AiTheme\Admin\Surveys\RateAiSite;
use Hostinger\AiTheme\Builder\BundleSchema;
use Hostinger\AiTheme\Builder\Helper;
use Hostinger\AiTheme\Constants\GenerationConstant;
use Hostinger\AiTheme\Data\HostingerEcommerceHelper;
use Hostinger\AiTheme\Data\NewEngineExperiment;
use Hostinger\AiTheme\Data\WebsiteTypeHelper;
use Hostinger\WpHelper\Utils;
use Hostinger\WpMenuManager\Menus;

defined( 'ABSPATH' ) || exit;

class Assets {
    private const VALID_WEBSITE_TYPES = array(
        'business',
        'online store',
        'blog',
        'landing page',
        'booking',
        'portfolio',
        'affiliate-marketing',
        'other',
    );
    private Utils $utils;
    private ?RateAiSite $rateAiSite;

    public function __construct( Utils $utils, ?RateAiSite $rateAiSite = null ) {
        $this->utils = $utils;
        $this->rateAiSite = $rateAiSite;

        $admin_path = parse_url( admin_url(), PHP_URL_PATH );
        if ( $this->utils->isThisPage( $admin_path . 'admin.php?page=' . AdminMenu::AI_BUILDER_MENU_SLUG )
            || $this->utils->isThisPage( $admin_path . 'admin.php?page=' . AdminMenu::AI_BUILDER_V2_MENU_SLUG )
            || $this->utils->isThisPage( $admin_path . 'admin.php?page=' . Menus::MENU_SLUG ) ) {
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
        }
    }

	/**
	 * Enqueues styles for the Hostinger admin pages.
	 */
	public function admin_styles(): void {
		wp_enqueue_style( 'hostinger_ai_websites_main_styles', HOSTINGER_AI_WEBSITES_ASSETS_URL . '/css/main.min.css', array(), filemtime( HOSTINGER_AI_WEBSITES_THEME_PATH . '/assets/css/main.min.css' ), );
	}

	/**
	 * Enqueues scripts for the Hostinger admin pages.
	 */
	public function admin_scripts(): void {
        wp_enqueue_script(
            'hostinger_ai_websites_main_scripts',
            HOSTINGER_AI_WEBSITES_ASSETS_URL . '/js/main.min.js',
            array(
                'jquery',
                'wp-i18n',
            ),
	        filemtime( HOSTINGER_AI_WEBSITES_THEME_PATH . '/assets/js/main.min.js'),
            false
        );

        $site_url = add_query_arg( 'LSCWP_CTRL', 'before_optm', get_site_url() . '/' );

        $language_code = 'en';
        $locale        = get_locale();
        if ( ! empty( $locale ) ) {
            $language_code = substr( $locale, 0, 2 );
        }

        $localize_data = array(
            'site_url'                      => $site_url,
            'plugin_url'                    => get_stylesheet_directory_uri() . '/',
            'hostinger_admin_url'           => admin_url( 'admin.php?page=hostinger' ),
            'homepage_woocommerce_url'      => admin_url( 'admin.php?page=wc-admin' ),
            'homepage_ecommerce_url'        => admin_url( 'admin.php?page=hostinger-ecommerce' ),
            'admin_url'                     => admin_url( 'admin-ajax.php' ),
            'website_type'                  => WebsiteTypeHelper::get_website_types(),
            'translations'                  => AdminTranslations::getValues(),
            'content_generated'             => (int) ! empty( get_option( 'hostinger_ai_version' ) ),
            'rest_base_url'                 => esc_url_raw( rest_url() ),
            'nonce'                         => wp_create_nonce( 'wp_rest' ),
            'ajax_nonce'                    => wp_create_nonce( 'updates' ),
            'homepage_editor_url'           => $this->get_homepage_site_editor_url(),
            'is_survey_enabled'             => $this->rateAiSite && $this->rateAiSite->isSurveyEnabled(),
            'site_locale'                   => $language_code,
            'prefill_website_type'          => $this->get_prefill_option( 'hostinger_login_website_type', true ),
            'prefill_brand_name'            => $this->get_prefill_option( 'hostinger_login_brand_name' ),
            'prefill_website_description'   => $this->get_prefill_option( 'hostinger_login_website_description' ),
            'admin_email'                   => sanitize_email( (string) get_option( 'admin_email', '' ) ),
            'current_logo'                  => $this->get_current_logo(),
            'original_logo'                 => $this->get_original_logo(),
            'current_colors'                => $this->get_current_colors(),
            'current_color_options'         => $this->get_current_color_options(),
            'current_font_options'          => $this->get_current_font_options(),
            'current_heading_font'          => get_option( 'hostinger_ai_font', '' ),
            'current_body_font'             => get_option(
                'hostinger_ai_body_font_override',
                get_option( 'hostinger_ai_body_font', '' )
            ),
            'original_colors'               => get_option( BundleSchema::OPTION_ORIGINAL_COLORS, array() ),
            'original_font_pair'            => get_option( BundleSchema::OPTION_ORIGINAL_FONTS, array() ),
            'current_pages'                 => $this->get_current_pages(),
            'theme_version'                 => wp_get_theme()->get( 'Version' ),
            'max_pages'                     => GenerationConstant::MAX_PAGES,
            'max_pages_legacy'              => GenerationConstant::MAX_PAGES_LEGACY,
            'new_engine_experiment'         => NewEngineExperiment::is_enabled(),
            'site_generated'                => Helper::is_site_generated(),
        );

        if ( HostingerEcommerceHelper::is_active() ) {
            $localize_data['is_ecommerce_flow'] = true;

            $ecommerce_prompt = HostingerEcommerceHelper::get_prompt();
            if ( $ecommerce_prompt !== '' ) {
                $localize_data['prefill_website_description'] = $ecommerce_prompt;
            }
        }

        wp_localize_script(
            'hostinger_ai_websites_main_scripts',
            'hostinger_ai_websites',
            $localize_data
        );

        wp_enqueue_script(
            'hostinger_ai_websites_admin_scripts',
            HOSTINGER_AI_WEBSITES_ASSETS_URL . '/js/admin.min.js',
            array(
                'jquery',
                'wp-i18n',
            ),
	        filemtime( HOSTINGER_AI_WEBSITES_THEME_PATH . '/assets/js/admin.min.js' ),
            false
        );
	}

    private function get_homepage_site_editor_url(): string {
        $query_args = array(
            'hostinger_builder_redirect' => 'edit',
        );

        return add_query_arg( $query_args, admin_url( 'index.php' ) );
    }

    private function get_current_logo(): string {
        $logo_id = get_theme_mod( 'custom_logo', 0 );
        if ( ! $logo_id ) {
            return '';
        }

        return wp_get_attachment_image_url( $logo_id, 'full' ) ?: '';
    }

    private function get_original_logo(): string {
        $logo_id = (int) get_option( BundleSchema::OPTION_ORIGINAL_LOGO, 0 );

        if ( $logo_id <= 0 ) {
            return '';
        }

        $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );

        if ( ! $logo_url ) {
            return '';
        }

        return $logo_url;
    }

    private function get_current_colors(): array {
        $colors = get_option( 'hostinger_ai_colors', array() );

        return is_array( $colors ) ? $colors : array();
    }

    private function get_current_color_options(): array {
        $options = get_option( 'hostinger_ai_color_options', array() );

        return is_array( $options ) ? $options : array();
    }

    private function get_current_font_options(): array {
        $options = get_option( 'hostinger_ai_font_options', array() );

        return is_array( $options ) ? $options : array();
    }

    private function get_current_pages(): array {
        $pages = get_option( 'hostinger_ai_pages_structure', array() );

        return is_array( $pages ) ? $pages : array();
    }

    private function get_prefill_option( string $option_name, bool $validate_website_type = false ): string {
        $value = get_option( $option_name, '' );

        if ( empty( $value ) ) {
            return '';
        }

        delete_option( $option_name );

        $value = sanitize_text_field( $value );

        if ( $validate_website_type ) {
            $normalized = WebsiteTypeHelper::normalize( $value );
            $match      = null;
            foreach ( self::VALID_WEBSITE_TYPES as $valid ) {
                if ( WebsiteTypeHelper::normalize( $valid ) === $normalized ) {
                    $match = $valid;
                    break;
                }
            }
            if ( $match === null ) {
                return '';
            }
            return $match;
        }

        return $value;
    }
}
