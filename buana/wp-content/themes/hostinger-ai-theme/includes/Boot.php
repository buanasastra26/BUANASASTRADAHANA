<?php
namespace Hostinger\AiTheme;

use Hostinger\AiTheme\Admin\Assets as AdminAssets;
use Hostinger\AiTheme\Admin\Hooks as AdminHooks;
use Hostinger\AiTheme\Admin\Menu as AdminMenu;
use Hostinger\AiTheme\Builder\AffiliateBuilder;
use Hostinger\AiTheme\Compatibility\LiteSpeedCache;
use Hostinger\AiTheme\Builder\DebugMode;
use Hostinger\AiTheme\Builder\HostingerEcommerceBuilder;
use Hostinger\AiTheme\Builder\HostingerEcommerceStoreConnector;
use Hostinger\AiTheme\Builder\ElementorBuilder;
use Hostinger\AiTheme\Builder\Fonts;
use Hostinger\AiTheme\Builder\GenerationClient;
use Hostinger\AiTheme\Builder\SiteApplier;
use Hostinger\AiTheme\Builder\TemplatePartVisibility;
use Hostinger\AiTheme\Builder\HostingerReachBuilder;
use Hostinger\AiTheme\Cli\ApplySiteCommand;
use Hostinger\AiTheme\Data\NewEngineExperiment;
use Hostinger\AiTheme\Builder\ImageManager;
use Hostinger\AiTheme\Builder\RequestClient;
use Hostinger\AiTheme\Builder\ShowcaseBuilder;
use Hostinger\AiTheme\Builder\WebsiteTypeDetector;
use Hostinger\AiTheme\Cli\ShowcaseCommand;
use Hostinger\AiTheme\Builder\Seo;
use Hostinger\AiTheme\Builder\WebsiteBuilder;
use Hostinger\AiTheme\Builder\WooBuilder;
use Hostinger\AiTheme\Builder\Elementor\PostBuildCustomizations;
use Hostinger\AiTheme\Elementor\WidgetManager;
use Hostinger\AiTheme\Elementor\ScrollAnimation;
use Hostinger\AiTheme\Rest\BlockTypeRoutes;
use Hostinger\AiTheme\Rest\BuilderRoutes;
use Hostinger\AiTheme\Rest\BundleApplierRoutes;
use Hostinger\AiTheme\Rest\GenerationRoutes;
use Hostinger\AiTheme\Rest\LogoRoutes;
use Hostinger\AiTheme\Rest\Routes;
use Hostinger\AiTheme\Rest\VersionRoutes;
use Hostinger\AiTheme\Settings\Theme as ThemeSettings;
use Hostinger\AiTheme\Versions\Schema as VersionSchema;
use Hostinger\AiTheme\Shortcodes\ShortcodesManager;
use Hostinger\AiTheme\Admin\Surveys\RateAiSite;
use Hostinger\AiTheme\Admin\Surveys\WebsiteBuilderExperience;
use Hostinger\Amplitude\AmplitudeLoader;
use Hostinger\Amplitude\AmplitudeManager;
use Hostinger\Surveys\Loader;
use Hostinger\Surveys\Rest as SurveysRest;
use Hostinger\Surveys\SurveyManager;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Constants;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils as Helper;
use Hostinger\WpMenuManager\Manager;

defined( 'ABSPATH' ) || exit;

class Boot {
    public function run(): void {
        $this->load_hostinger_packages();
        $this->load_dependencies();
        $this->set_locale();
    }

    /**
     * @return void
     */
    public function hostinger_load_menus(): void {
        $manager = Manager::getInstance();
        $manager->boot();
    }

    /**
     * @return void
     */
    public function hostinger_load_amplitude(): void {
        $amplitude = AmplitudeLoader::getInstance();
        $amplitude->boot();
    }

    public function hostinger_add_surveys(): void {
        $surveys = Loader::getInstance();
        $surveys->boot();
    }

    /**
     * @return void
     */
    private function load_hostinger_packages(): void {
        if ( ! has_action( 'plugins_loaded', 'hostinger_load_menus' ) ) {
            add_action( 'after_setup_theme', array( $this, 'hostinger_load_menus' ) );
        }

        if ( ! has_action( 'plugins_loaded', 'hostinger_load_amplitude' ) ) {
            add_action( 'after_setup_theme', array( $this, 'hostinger_load_amplitude' ) );
        }

        if ( ! has_action( 'plugins_loaded', 'hostinger_add_surveys' ) ) {
            add_action( 'after_setup_theme', array( $this, 'hostinger_add_surveys' ) );
        }
    }

    private function load_dependencies(): void {
        $this->maybe_set_cli_host();

        $assets          = new Assets();
        $theme_settings  = new ThemeSettings();
        $updates         = new Updates();
        $updates->updates();

        $gutenbergBlocks = new GutenbergBlocks();
        $litespeedCache = new LiteSpeedCache();

        if ( did_action( 'elementor/loaded' ) ) {
            $elementorWidgets = new WidgetManager();
            $elementorScrollAnimation = new ScrollAnimation();
            $elementorPostBuildCustomizations = new PostBuildCustomizations();
        }

        $shortcodes = new ShortcodesManager();

        $helper         = new Helper();
        $config_handler = new Config();
        $default_headers = [
            Config::TOKEN_HEADER  => $helper::getApiToken(),
            Config::DOMAIN_HEADER => $helper->getHostInfo(),
            'Content-Type' => 'application/json'
        ];
        $client         = new Client( $config_handler->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI ), $default_headers );

        $request_client = new RequestClient( $client );

        ( new DebugMode() )->register();
        ( new TemplatePartVisibility() )->init();

        $image_manager = new ImageManager();
        $affiliate_builder = new AffiliateBuilder();
        $elementor_builder = new ElementorBuilder();
        $woo_builder = new WooBuilder( $image_manager );
        $hostinger_reach_builder = new HostingerReachBuilder();
        $ecommerce_builder = new HostingerEcommerceBuilder();
        $fonts = new Fonts();
        $wh_api_client = new RequestClient( new Client( $config_handler->getConfigValue( 'base_proxy_rest_uri', HOSTINGER_WP_PROXY_API_URI ), $default_headers ) );

        $website_builder = new WebsiteBuilder( $request_client, $image_manager, $affiliate_builder, $elementor_builder, $woo_builder, $hostinger_reach_builder, $ecommerce_builder, $fonts, $wh_api_client );
        $website_builder->init();

        ( new HostingerEcommerceStoreConnector( $ecommerce_builder ) )->init();

        $builder_routes = new BuilderRoutes( $website_builder );

        VersionSchema::maybe_install();

        $block_type_routes = new BlockTypeRoutes();
        $logo_routes = new LogoRoutes();
        $version_routes = new VersionRoutes();
        $routes = new Routes( $builder_routes, $block_type_routes, $logo_routes, $version_routes );
        $routes->init();

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            $showcase_builder      = new ShowcaseBuilder( $request_client );
            $website_type_detector = new WebsiteTypeDetector( $wh_api_client );

            \WP_CLI::add_command(
                'hostinger-ai-theme showcase',
                new ShowcaseCommand( $website_builder, $showcase_builder, $website_type_detector )
            );
        }

        // Envelope applier — strangler path beside the legacy builder. Inert
        // until an envelope is applied (gated on the applier-mode option).
        $site_applier = new SiteApplier( $affiliate_builder );
        $site_applier->init();
        $bundle_applier_routes = new BundleApplierRoutes( $site_applier );
        $bundle_applier_routes->init();

        $generation_client = new GenerationClient(
            new Client( $config_handler->getConfigValue( 'base_proxy_rest_uri', HOSTINGER_WP_PROXY_API_URI ), $default_headers )
        );
        $generation_routes = new GenerationRoutes(
            $generation_client,
            $site_applier,
            $wh_api_client,
            $elementor_builder,
            $woo_builder,
            $affiliate_builder
        );
        $generation_routes->init();

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'hostinger-ai apply-site', new ApplySiteCommand( $site_applier ) );
        }

        $utils = new Helper();
        $redirects = new Redirects( $utils );
        $seo = new Seo();
        $hooks = new Hooks( $image_manager );

        if ( is_admin() ) {
            $this->load_admin_dependencies();
        }
    }

    /**
     * Under WP-CLI there is no HTTP request, so $_SERVER['HTTP_HOST'] is empty and
     * Utils::getHostInfo() / DomainResolver resolve to an empty domain. That breaks
     * domain-dependent API calls (image/video search), leaving placeholders unreplaced.
     * Populate the host from the site URL before any API client is constructed.
     *
     * @return void
     */
    private function maybe_set_cli_host(): void {
        if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) || ! empty( $_SERVER['HTTP_HOST'] ) ) {
            return;
        }

        $site_url = get_site_url();
        $host     = wp_parse_url( $site_url, PHP_URL_HOST );

        if ( empty( $host ) ) {
            return;
        }

        $port = wp_parse_url( $site_url, PHP_URL_PORT );

        $_SERVER['HTTP_HOST'] = $host . ( $port ? ':' . $port : '' );
    }

    private function set_locale() {
        $plugin_i18n = new I18n();
    }


    private function load_admin_dependencies(): void
    {
        $image_manager = new ImageManager();
        $helper        = new Helper();

        $config = new Config();
        $client = new Client(
            $config->getConfigValue( 'base_rest_uri', Constants::HOSTINGER_REST_URI ),
            [
                Config::TOKEN_HEADER  => $helper->getApiToken(),
                Config::DOMAIN_HEADER => $helper->getHostInfo(),
            ]
        );

        $rateAiSite = null;

        if ( class_exists( SurveyManager::class ) ) {
            $surveysRest   = new SurveysRest( $client );
            $surveyManager = new SurveyManager( $helper, $config, $surveysRest );

            $surveys = new WebsiteBuilderExperience( $surveyManager );
            $surveys->init();

            $rateAiSite = new RateAiSite( $surveyManager );
        }

        $amplitude_manager = new AmplitudeManager( $helper, $config, $client );
        ( new NewEngineExperiment( $client, $helper, $amplitude_manager ) )->register();

        new AdminAssets( $helper, $rateAiSite );
        new AdminMenu();
        new AdminHooks( $image_manager );
    }
}
