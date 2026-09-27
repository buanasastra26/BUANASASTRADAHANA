<?php

namespace Hostinger\AiTheme\Builder;

use Exception;
use Hostinger\AiTheme\Builder\Elementor\CssCache;
use Hostinger\AiTheme\Builder\Elementor\KitManager;
use Hostinger\AiTheme\Constants\BuilderType;
use Hostinger\AiTheme\Constants\GenerationConstant;
use Hostinger\AiTheme\Constants\PreviewImageConstant;
use Hostinger\AiTheme\Data\HostingerEcommerceHelper;
use Hostinger\AiTheme\Data\WebsiteTypeHelper;

defined( 'ABSPATH' ) || exit;

class WebsiteBuilder {
    /**
     * @var RequestClient
     */
    private RequestClient $request_client;
    private ImageManager $image_manager;
    private AffiliateBuilder $affiliate_builder;
    private ElementorBuilder $elementor_builder;
    private HostingerReachBuilder $hostinger_reach_builder;
    private WooBuilder $woo_builder;
    private HostingerEcommerceBuilder $ecommerce_builder;
    private Fonts $fonts;
    private RequestClient $wh_api_client;
    private const WOO_BLOCKS_PATH = HOSTINGER_AI_WEBSITES_THEME_PATH . DIRECTORY_SEPARATOR . 'blocks' . DIRECTORY_SEPARATOR . 'woo' . DIRECTORY_SEPARATOR;
    private const ELEMENTOR_BLOCKS_PATH = HOSTINGER_AI_WEBSITES_THEME_PATH . DIRECTORY_SEPARATOR . 'blocks' . DIRECTORY_SEPARATOR . 'elementor' . DIRECTORY_SEPARATOR;

    public function __construct( RequestClient $request_client, ImageManager $image_manager, AffiliateBuilder $affiliate_builder, ElementorBuilder $elementor_builder, WooBuilder $woo_builder, HostingerReachBuilder $hostinger_reach_builder, HostingerEcommerceBuilder $ecommerce_builder, Fonts $fonts, RequestClient $wh_api_client ) {
        $this->request_client = $request_client;
        $this->image_manager = $image_manager;
        $this->affiliate_builder = $affiliate_builder;
        $this->elementor_builder = $elementor_builder;
        $this->woo_builder = $woo_builder;
        $this->hostinger_reach_builder = $hostinger_reach_builder;
        $this->ecommerce_builder = $ecommerce_builder;
        $this->fonts = $fonts;
        $this->wh_api_client = $wh_api_client;
    }

    public function init() {
        add_filter( 'wp_theme_json_data_theme', [ $this, 'update_theme_json'], 999 );
    }

    public function update_theme_json( $theme_json) {

        $hostinger_ai_version = get_option( 'hostinger_ai_version', false );

        if(empty($hostinger_ai_version)) {
            return $theme_json;
        }

		$heading_font = $this->get_theme_json_heading_typography( $theme_json );
		$body_font = $this->get_theme_json_typography( $theme_json, $heading_font );

        $new_data = array(
            'version'  => $hostinger_ai_version,
            'settings' => array(
                'color' => $this->get_theme_json_colors()
            ),
            'styles' => array(
                'color' => array(
                    'background' => 'var(--wp--preset--color--page-background)',
                    'text'       => 'var(--wp--preset--color--page-text)',
                ),
                'typography' => array(
	                'fontFamily' => $body_font,
                ),
                'elements' => array(
					'heading' => array(
						'typography' => array(
							'fontFamily' => $heading_font,
						),
					)
                )
            )
        );

        return $theme_json->update_with( $new_data );

    }

    /**
     * Get a random main font from the theme.json font family list.
     *
     * @param \WP_Theme_JSON_Data $theme_json
     * @return string The font family name.
     */
    protected function get_theme_json_heading_typography( \WP_Theme_JSON_Data $theme_json ): string {
        return $this->fonts->get_main_font( $theme_json->get_data() );
    }

	/**
	 * Get the body font based on the main font.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json
	 * @param string $main_font
	 *
	 * @return string The font family name.
	 */
	protected function get_theme_json_typography( \WP_Theme_JSON_Data $theme_json, string $main_font ): string {
		return $this->fonts->get_body_font( $theme_json->get_data(), $main_font );
	}

    protected function get_theme_json_colors() {
        $hostinger_ai_colors = get_option( 'hostinger_ai_colors', false );

        if ( ! empty( $hostinger_ai_colors['color_palette'] ) ) {
            $hostinger_ai_colors = $hostinger_ai_colors['color_palette'];
        }

        if ( empty( $hostinger_ai_colors ) || empty( $hostinger_ai_colors['gradients'] ) ) {
            return array();
        }

        $gradient_key = array_key_first($hostinger_ai_colors['gradients']);

        return array(
            'palette' => [
                [
                    'slug' => 'color1',
                    'color' => !empty($hostinger_ai_colors['color1']) ? $hostinger_ai_colors['color1'] : '',
                    'name' => 'Color 1 (Section backgrounds)'
                ],
                [
                    'slug' => 'color2',
                    'color' => !empty($hostinger_ai_colors['color2']) ? $hostinger_ai_colors['color2'] : '',
                    'name' => 'Color 2 (Section backgrounds)'
                ],
                [
                    'slug' => 'color3',
                    'color' => !empty($hostinger_ai_colors['color3']) ? $hostinger_ai_colors['color3'] : '',
                    'name' => 'Color 3 (Button background)'
                ],
                [
                    'slug' => 'light',
                    'color' => !empty($hostinger_ai_colors['light']) ? $hostinger_ai_colors['light'] : '',
                    'name' => 'Light (Text on Color 2 and Gradient)'
                ],
                [
                    'slug' => 'dark',
                    'color' => !empty($hostinger_ai_colors['dark']) ? $hostinger_ai_colors['dark'] : '',
                    'name' => 'Dark (Text on Light and Color 1)'
                ],
                [
                    'slug' => 'grey',
                    'color' => !empty($hostinger_ai_colors['grey']) ? $hostinger_ai_colors['grey'] : '',
                    'name' => 'Grey (Form borders)'
                ],
                [
                    'slug' => 'page-background',
                    'color' => !empty($hostinger_ai_colors['page_background']) ? $hostinger_ai_colors['page_background'] : '#ffffff',
                    'name' => 'Page background'
                ],
                [
                    'slug' => 'page-text',
                    'color' => !empty($hostinger_ai_colors['page_text']) ? $hostinger_ai_colors['page_text'] : '#000000',
                    'name' => 'Page text'
                ]
            ],
            'gradients' => [
                [
                    'slug' => 'gradient-one',
                    'gradient' => 'linear-gradient(135deg, '.(!empty($hostinger_ai_colors['color3']) ? $hostinger_ai_colors['color3'] : '').' 0%, '.(!empty($hostinger_ai_colors['gradients']) ? $hostinger_ai_colors['gradients'][$gradient_key]['gradient'] : '').' 100%)',
                    'name' => 'Section background gradient'
                ]
            ]
        );
    }

    /**
     * @param string $description
     *
     * @return bool
     */
    public function generate_colors( string $description ): bool {
        $colors = new Colors( $description );
        $colors->setRequestClient( $this->request_client );
        $colors->setWhApiClient( $this->wh_api_client );

        return $colors->generate_colors();
    }

    public function generate_color_options( string $description ): array {
        $colors = new Colors( $description );
        $colors->setRequestClient( $this->request_client );
        $colors->setWhApiClient( $this->wh_api_client );

        return $colors->generate_color_options();
    }

    public function generate_font_options(): array {
        $this->fonts->setWhApiClient( $this->wh_api_client );

        return $this->fonts->generate_font_options();
    }

    public function set_colors( string $description, array $colors_palette): bool {
        $colors = new Colors( $description );
        $colors->setRequestClient( $this->request_client );
        $colors->setWhApiClient( $this->wh_api_client );

        return $colors->set_colors( $colors_palette );
    }

    public function generate_structure( string $brand_name, array $website_type, string $description, array $pages = [] ): bool {
        $structure = new Structure( $brand_name, $website_type, $description );
        $structure->set_request_client( $this->request_client );

        $website_structure = $structure->generate_structure( $pages );

        if ( empty( $website_structure ) ) {
            return false;
        }

        $this->mark_blog_section($website_structure);

        update_option( 'hostinger_ai_website_structure', $website_structure );

        return true;
    }

    public function get_pages_list( string $brand_name, array $website_type, string $description ): array {
        $structure = new Structure( $brand_name, $website_type, $description );
        $structure->set_request_client( $this->request_client );

        $website_structure = $structure->generate_structure();

        return array_map( function ( $page_data ) {
            return array(
                'id'   => $page_data['page'],
                'name' => mb_convert_case( $page_data['page'], MB_CASE_TITLE, 'UTF-8' ),
            );
        }, $website_structure );
    }

    public function enable_plugins(): array {
        $plugins = array(
            'elementor'   => array(
                'builder'          => $this->elementor_builder,
                'enabled_callback' => fn() => $this->elementor_builder->is_plugin_active(),
            ),
            'woocommerce' => array(
                'builder'          => $this->woo_builder,
                'enabled_callback' => fn() => get_option( 'hostinger_ai_woo', false ),
            ),
            'affiliate'   => array(
                'builder'          => $this->affiliate_builder,
                'enabled_callback' => fn() => get_option( 'hostinger_ai_affiliate', false ),
            ),
            'reach'       => array(
                'builder'          => $this->hostinger_reach_builder,
                'enabled_callback' => fn() => true,
            ),
            'ecommerce'   => array(
                'builder'          => $this->ecommerce_builder,
                'enabled_callback' => fn() => HostingerEcommerceHelper::is_active(),
            ),
        );

        $results = array();

        foreach ( $plugins as $name => $config ) {
            $results[ $name ] = $this->process_plugin_boot( $config['builder'], $config['enabled_callback'] );
        }

        return $results;
    }

    private function process_plugin_boot( AbstractPluginBuilder $builder, callable $enabled_callback ): array {
        $result = $builder->boot();

        if ( is_wp_error( $result ) ) {
            return array( 'status' => 'failed', 'error' => $result->get_error_message() );
        }

        if ( $result === true && $enabled_callback() ) {
            return array( 'status' => 'enabled', 'error' => null );
        }

        return array( 'status' => 'skipped', 'error' => null );
    }

    public function generate_content( string $brand_name, array $website_type, string $description ): bool {
        $blog_content_needed = get_option( 'hostinger_ai_blog_needed', false );
        $woocommerce         = get_option( 'hostinger_ai_woo', false );

        $structure = new Structure( $brand_name, $website_type, $description );
        $structure->set_request_client( $this->request_client );

        $website_structure = get_option( 'hostinger_ai_website_structure' );

        $page_data = $structure->generate_builder_data( $website_structure );

        $content = $structure->generate_content( $page_data );

        if ( empty( $content ) ) {
            throw new Exception( 'There was an error generating a content' );
        }

        $content = $structure->merge_content( $page_data, $content );

        update_option( 'hostinger_ai_website_content', $content );

        if ( ! empty( $blog_content_needed ) ) {
            $blog_builder = new BlogBuilder( $brand_name, $website_type, $description );
            $blog_builder->generate_blog();
        }

        if ( ! empty( $woocommerce ) ) {
            $this->woo_builder->generate_products( $content );
        }

		$this->hostinger_reach_builder->generate_form();

        $language = get_option( 'hostinger_ai_selected_language', 'en_US' );
        Helper::install_and_set_language( $language );

        return true;
    }

    /**
     * @param array $content
     *
     * @return bool
     */
    public function build_content( array $content ): bool {
        $locale_switched = Helper::switch_to_selected_locale();

        try {
            ImageManager::reset_image_tracking();

            $page_builder = new PageBuilder( $content );

            $options = get_option( 'hostinger_ai_theme_display_options', [] );

            $this->maybe_delete_woo_pages();

            $pages = $page_builder->build_pages();

            $home_key = array_key_first( $pages );

            if ( ! empty( $pages[$home_key] ) ) {
                update_option( 'show_on_front', 'page' );
                update_option( 'page_on_front', $pages[$home_key]['page_id'] );
            }

            $this->maybe_build_woo_pages( $pages );

            $navigation_builder = new NavigationBuilder( $pages );
            $navigation_builder->updateMenus();

            // Handle header visibility separately
            $this->update_header_visibility( $content );

            $this->apply_elementor_typography();
            $this->apply_elementor_global_settings();

            self::clear_elementor_cache();

            // Delete temporary saved website structure pages.
            delete_option( 'hostinger_ai_website_structure' );
        } finally {
            Helper::restore_selected_locale( $locale_switched );
        }

        return true;
    }

    public function clear_ai_data( array $new_website_type, string $new_builder_type = '' ): void {
        // Prompt.
        delete_option('hostinger_ai_brand_name');
        delete_option('hostinger_ai_description');

        $current_builder_type = get_option( 'hostinger_ai_builder_type', '' );

        self::reconcile_plugins( $new_website_type, $current_builder_type, $new_builder_type );

        delete_option( 'hostinger_ai_affiliate' );
        delete_option( 'hostinger_ai_woo' );

        // Fonts.
        delete_option('hostinger_ai_version');
        delete_option('hostinger_ai_font');
        delete_option('hostinger_ai_font_options');
        delete_option('hostinger_ai_body_font');
        delete_option('hostinger_ai_body_font_override');
        delete_option('hostinger_elementor_typography_set');

        // Images.
        delete_option('hostinger_ai_used_images');

        // Structure
        delete_option('hostinger_ai_website_structure');

        // Content
        delete_option('hostinger_ai_website_content');
    }

    public static function reconcile_plugins( array $new_website_type, string $current_builder_type, string $new_builder_type ): void {
        $plugins = self::get_plugins_to_deactivate( $new_website_type, $current_builder_type, $new_builder_type );

        foreach ( $plugins as $plugin_file ) {
            self::deactivate_plugin( $plugin_file );
        }
    }

    /**
     * @return bool
     */
    public function clear_ai_content(): bool {
        remove_theme_mod( 'custom_logo' );

        ( new GenerationCleaner() )->sweep();
        ( new ProductCategoryManager() )->clear_created_categories();

        delete_option( 'hostinger_ai_created_pages' );
        delete_option( 'hostinger_ai_created_blog_posts' );
        delete_option( 'hostinger_ai_created_products' );
        delete_option( 'hostinger_ai_blog_needed' );

        return true;
    }

    public function mark_blog_section( array $structure ): void {
        if (!empty($structure)) {
            foreach ($structure as $page_data) {
                if (!empty($page_data['sections'])) {
                    $sections = array_column($page_data['sections'], 'section');
                    if (array_search('blog-posts', $sections) !== false) {
                        update_option('hostinger_ai_blog_needed', 1);
                        break;
                    }
                }
            }
        }
    }

    /**
     * Handle header visibility based on website type
     *
     * @param array $content The content configuration
     */
    private function update_header_visibility( array $content ): void {
        $options = get_option( 'hostinger_ai_theme_display_options', [] );

        $is_landing_page = WebsiteTypeHelper::is_only_website_type( 'landing page' );
        $header_hidden   = isset( $options['hide_header'] );

        if ( $is_landing_page && ! $header_hidden ) {
            $options['hide_header'] = 1;
            update_option( 'hostinger_ai_theme_display_options', $options );
        } elseif ( ! $is_landing_page && $header_hidden ) {
            unset( $options['hide_header'] );
            update_option( 'hostinger_ai_theme_display_options', $options );
        }
    }

    private static function get_plugins_to_deactivate( array $new_website_type, string $current_builder_type, string $new_builder_type ): array {
        $plugins = array();

        if ( $current_builder_type === BuilderType::ELEMENTOR && $new_builder_type === BuilderType::GUTENBERG ) {
            $plugins[] = 'elementor/elementor.php';
        }

        if ( ! in_array( 'affiliate-marketing', $new_website_type, true ) ) {
            $plugins[] = 'hostinger-affiliate-plugin/hostinger-affiliate-plugin.php';
        }

        if ( ! WebsiteTypeHelper::contains( $new_website_type, 'online store' ) ) {
            $plugins[] = 'woocommerce/woocommerce.php';
        }

        return $plugins;
    }

    private static function deactivate_plugin( string $plugin_file ): void {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! is_plugin_active( $plugin_file ) ) {
            return;
        }

        deactivate_plugins( $plugin_file );

        if ( is_plugin_active( $plugin_file ) ) {
            error_log( 'Hostinger AI Theme: Failed to deactivate plugin ' . $plugin_file );
        }
    }

    private function maybe_build_woo_pages(array $pages): array {
        if (empty(get_option('hostinger_ai_woo'))) {
            return array();
        }

        $new_pages = array();

        if ( ! empty( $pages['shop'] ) ) {
            update_option( 'woocommerce_shop_page_id', $pages['shop']['page_id'] );
        }

        $cart_page = $this->create_woo_page(
            'cart.html',
            __('Cart', 'hostinger-ai-theme'),
            'woocommerce_cart_page_id'
        );
        if ($cart_page) {
            $new_pages['cart'] = $cart_page;
        }

        $checkout_page = $this->create_woo_page(
            'checkout.html',
            __('Checkout', 'hostinger-ai-theme'),
            'woocommerce_checkout_page_id'
        );
        if ($checkout_page) {
            $new_pages['checkout'] = $checkout_page;
        }

        $myaccount_page = $this->create_woo_page(
            'my-account.html',
            __('My Account', 'hostinger-ai-theme'),
            'woocommerce_myaccount_page_id'
        );
        if ($myaccount_page) {
            $new_pages['myaccount'] = $myaccount_page;
        }

        if (!empty($new_pages)) {
            $existing_pages = get_option('hostinger_ai_created_pages', []);
            update_option('hostinger_ai_created_pages', array_merge($existing_pages, $new_pages));
        }

        return $new_pages;
    }

    private function create_woo_page( string $template_file, string $title, string $option_name ): array {
        $builder_type = get_option( 'hostinger_ai_builder_type', '' );

        if ( $builder_type === 'elementor' ) {
            return $this->create_elementor_woo_page( $template_file, $title, $option_name );
        }

        return $this->create_gutenberg_woo_page( $template_file, $title, $option_name );
    }

    private function create_gutenberg_woo_page( string $template_file, string $title, string $option_name ): array {
        $template_path = self::WOO_BLOCKS_PATH . $template_file;

        if (!file_exists($template_path)) {
            return array();
        }

        $content = file_get_contents($template_path);
        if ($content === false) {
            return array();
        }
        $page_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page',
            'meta_input' => array(
                GenerationConstant::META_KEY => '1',
            ),
        ]);

        if (!$page_id || is_wp_error($page_id)) {
            return array();
        }

        update_option($option_name, $page_id);

        return array(
            'title' => $title,
            'page_id' => $page_id,
        );
    }

    private function create_elementor_woo_page( string $template_file, string $title, string $option_name ): array {
        $json_file = str_replace( '.html', '.json', $template_file );
        $template_path = self::ELEMENTOR_BLOCKS_PATH . $json_file;

        if ( ! file_exists( $template_path ) ) {
            return array();
        }

        $json_content = file_get_contents( $template_path );
        if ( $json_content === false ) {
            return array();
        }

        $page_id = wp_insert_post( [
            'post_title'   => $title,
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'meta_input'   => array(
                GenerationConstant::META_KEY => '1',
            ),
        ] );

        if ( ! $page_id || is_wp_error( $page_id ) ) {
            return array();
        }

        $elementor_version = Helper::get_elementor_version();

        update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
        update_post_meta( $page_id, '_elementor_version', $elementor_version );
        Helper::save_elementor_data( $page_id, $json_content );

        update_option( $option_name, $page_id );

        return array(
            'title'   => $title,
            'page_id' => $page_id,
        );
    }

    private function maybe_delete_woo_pages(): void {
        if ( empty( get_option( 'hostinger_ai_woo' ) ) ) {
            return;
        }

        $woo_pages = [
            'woocommerce_shop_page_id',
            'woocommerce_cart_page_id',
            'woocommerce_checkout_page_id',
            'woocommerce_myaccount_page_id',
        ];

        foreach ( $woo_pages as $page_option ) {
            $page_id = get_option( $page_option );
            if ( $page_id ) {
                wp_delete_post( $page_id, true );
                delete_option( $page_option );
            }
        }
    }

    private function apply_elementor_typography(): void {
        $builder_type = get_option( 'hostinger_ai_builder_type', '' );

        if ( $builder_type !== BuilderType::ELEMENTOR ) {
            return;
        }

        $theme_json_data = [ 'settings' => wp_get_global_settings() ];
        $heading_font    = $this->fonts->get_main_font( $theme_json_data );
        $body_font       = $this->fonts->get_body_font( $theme_json_data, $heading_font );

        ( new KitManager() )->transform_custom_typography( $heading_font, $body_font );
    }

	private function apply_elementor_global_settings(): void {
		$builder_type = get_option( 'hostinger_ai_builder_type', '' );

		if ( $builder_type !== BuilderType::ELEMENTOR ) {
			return;
		}

		( new KitManager() )->set_global_image_border_radius( 8 );
		( new KitManager() )->set_global_container_mobile_layout_padding( 1 );
	}

    public static function clear_elementor_cache(): void {
        $builder_type = get_option( 'hostinger_ai_builder_type', '' );

        if( $builder_type !== BuilderType::ELEMENTOR ) {
            return;
        }

        CssCache::clear();
    }

    public static function regenerate_elementor_css(): void {
        CssCache::clear();
    }
}
