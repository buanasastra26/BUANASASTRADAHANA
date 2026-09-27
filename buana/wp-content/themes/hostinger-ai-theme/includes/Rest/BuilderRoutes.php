<?php
/**
 * Builder Rest API
 *
 */

namespace Hostinger\AiTheme\Rest;

use Hostinger\AiTheme\Admin\GutenbergPreferences;
use Hostinger\AiTheme\Builder\BundleSchema;
use Hostinger\AiTheme\Builder\ElementorBuilder;
use Hostinger\AiTheme\Builder\EnvelopeCustomization;
use Hostinger\AiTheme\Builder\Helper;
use Hostinger\AiTheme\Builder\HostingerEcommerceBuilder;
use Hostinger\AiTheme\Builder\GenerationState;
use Hostinger\AiTheme\Builder\TemplatePartVisibility;
use Hostinger\AiTheme\Builder\WooBuilder;
use Hostinger\AiTheme\Builder\HostingerReachBuilder;
use Hostinger\AiTheme\Constants\ApiRoutes;
use Hostinger\AiTheme\Constants\BuilderType;
use Hostinger\AiTheme\Builder\Elementor\CssCache;
use Hostinger\AiTheme\Builder\Elementor\KitManager;
use Hostinger\AiTheme\Builder\Fonts;
use Hostinger\AiTheme\Builder\LogoAdapter;
use Hostinger\AiTheme\Builder\WebsiteBuilder;
use Hostinger\AiTheme\Builder\SoftwareIdTrait;
use Hostinger\AiTheme\Builder\RequestClient;
use Hostinger\AiTheme\Builder\WebsiteTypeDetector;
use Hostinger\AiTheme\Data\HostingerEcommerceHelper;
use Hostinger\AiTheme\Data\WebsiteTypeHelper;
use Hostinger\AiTheme\Versions\VersionManager;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Utils;
use Hostinger\Amplitude\AmplitudeManager;
use Exception;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Http;
use WP_Theme_JSON_Resolver;

/**
 * Avoid possibility to get file accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Class for handling Settings Rest API
 */
class BuilderRoutes {
    use SoftwareIdTrait;
    private const AMPLITUDE_EVENT_CREATE = 'wordpress.ai_builder.create';
    private const AMPLITUDE_EVENT_CREATED = 'wordpress.ai_builder.created';
    private const AMPLITUDE_EVENT_FAILURE = 'wordpress.ai_builder.failure';
    private const SOFTWARE_ID_NOT_AVAILABLE = 'Software ID not available';
    private const EXCLUDED_PAGE_IDS = array(
        'terms & conditions',
        'privacy policy',
        'cookie policy',
    );

    /**
     * @var WebsiteBuilder
     */
    private WebsiteBuilder $website_builder;

    /**
     * @var RequestClient
     */
    private RequestClient $request_client;

    /**
     * @var RequestClient
     */
    private RequestClient $wh_api_client;

    /**
     * @var WebsiteTypeDetector
     */
    private WebsiteTypeDetector $website_type_detector;

    /**
     * @var AmplitudeManager
     */
    private AmplitudeManager $amplitude_manager;

    private LogoAdapter $logo_adapter;

    /**
     * @param WebsiteBuilder $website_builder
     */
    public function __construct( WebsiteBuilder $website_builder, ?LogoAdapter $logo_adapter = null ) {
        $this->website_builder = $website_builder;
        $this->logo_adapter    = $logo_adapter ?? new LogoAdapter();

        $helper = new Utils();
        $config_handler = new Config();
        $default_headers = [
            Config::TOKEN_HEADER  => $helper::getApiToken(),
            Config::DOMAIN_HEADER => $helper->getHostInfo(),
            'Content-Type' => 'application/json'
        ];

        $amplitude_headers = array_diff_key( $default_headers, array( 'Content-Type' => '' ) );

        $client = new Client(
            $config_handler->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI ),
            $default_headers
        );
        $this->request_client = new RequestClient( $client );

        $wh_api_client = new Client(
            $config_handler->getConfigValue( 'base_proxy_rest_uri', HOSTINGER_WP_PROXY_API_URI ),
            $default_headers
        );
        $this->wh_api_client = new RequestClient( $wh_api_client );

        $this->website_type_detector = new WebsiteTypeDetector( $this->wh_api_client );

        $amplitude_client = new Client(
            $config_handler->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI ),
            $amplitude_headers
        );
        $this->amplitude_manager = new AmplitudeManager( $helper, $config_handler, $amplitude_client );

        add_action(
            'hostinger_ai_color_contrast_adjusted',
            function ( string $color_role, string $original_hex, string $adjusted_hex, float $ratio_before, float $ratio_after ) {
                $this->amplitude_manager->sendRequest( Endpoints::AMPLITUDE_ENDPOINT, array(
                    'action'       => 'wordpress.ai_builder.color_contrast_adjusted',
                    'color_role'   => $color_role,
                    'original_hex' => $original_hex,
                    'adjusted_hex' => $adjusted_hex,
                    'ratio_before' => round( $ratio_before, 2 ),
                    'ratio_after'  => round( $ratio_after, 2 ),
                    'builder_type' => get_option( 'hostinger_ai_builder_type', 'unknown' ),
                ) );
            },
            10,
            5
        );
    }

    public function set_fonts( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $heading_font = $request->get_param( 'heading_font' );
        $body_font    = $request->get_param( 'body_font' );

        $theme_fonts    = wp_get_global_settings()['typography']['fontFamilies']['theme'] ?? [];
        $valid_families = array_column( $theme_fonts, 'fontFamily' );

        if ( ! in_array( $heading_font, $valid_families, true ) ) {
            return new WP_Error(
                'invalid_heading_font',
                __( 'Invalid heading font.', 'hostinger-ai-theme' ),
                [ 'status' => WP_Http::BAD_REQUEST ]
            );
        }

        if ( $body_font && ! in_array( $body_font, $valid_families, true ) ) {
            return new WP_Error(
                'invalid_body_font',
                __( 'Invalid body font.', 'hostinger-ai-theme' ),
                [ 'status' => WP_Http::BAD_REQUEST ]
            );
        }

        if ( $body_font ) {
            update_option( 'hostinger_ai_body_font_override', $body_font );
        } else {
            delete_option( 'hostinger_ai_body_font_override' );
        }

        update_option( 'hostinger_ai_font', $heading_font );
        update_option( 'hostinger_ai_version', time() );

        $theme_json_data = [ 'settings' => wp_get_global_settings() ];
        $resolved_body   = ( new Fonts() )->get_body_font( $theme_json_data, $heading_font );

        $builder_type = get_option( 'hostinger_ai_builder_type', 'gutenberg' );

		if ( get_option( 'hostinger_ai_woo', false ) ) {
			( new WooBuilder() )->boot();
		}

	    ( new HostingerReachBuilder() )->boot();

        if ( $builder_type === 'elementor' ) {
            ( new ElementorBuilder() )->boot();

            if ( BundleSchema::is_active() ) {
                EnvelopeCustomization::update_elementor_heading_fonts( $heading_font );
                $kit_manager = new KitManager();

                $kit_manager->apply_typography_families( $heading_font, $resolved_body );
            } else {
                ( new KitManager() )->transform_custom_typography( $heading_font, $resolved_body );
            }
        }

        if ( BundleSchema::is_active() ) {
            EnvelopeCustomization::update_typography( $heading_font, $resolved_body, $theme_fonts );
        }

        if ( $builder_type !== 'elementor' || BundleSchema::is_active() ) {
            if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
                WP_Theme_JSON_Resolver::clean_cached_data();
            }

            if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
                wp_clean_theme_json_cache();
            }
        }

        CssCache::bust_all( 'Hostinger AI Theme Fonts change' );

        return new WP_REST_Response( [ 'success' => true ], WP_Http::OK );
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function generate_colors( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $parameters = $request->get_params();

        $validation_error = $this->validate_required_fields( $parameters );
        if ( $validation_error ) {
            $this->send_failure_event( 'colors', 'validation', $validation_error->get_error_message() );
            return $validation_error;
        }

        $this->normalize_website_type( $parameters );

        GenerationState::start();

        $this->clear_ai_data( $parameters['website_type'], $parameters['builder_type'] );
        $this->handle_affiliate_type( $parameters );
        $this->handle_online_store_options( $parameters );
        $this->save_website_options( $parameters );

        $this->amplitude_manager->sendRequest( Endpoints::AMPLITUDE_ENDPOINT, array(
            'action'       => self::AMPLITUDE_EVENT_CREATE,
            'builder_type' => $parameters['builder_type'],
            'website_type' => implode( ', ', $parameters['website_type'] ),
            'language' => get_locale(),
        ) );

        update_option( 'hostinger_ai_version', time() );

        $data = array(
            'colors_generated' => true
        );

        $response = new \WP_REST_Response( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( \WP_Http::OK );

        return $response;
    }

    public function generate_color_options( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $description = $request->get_param( 'description' );

        if ( empty( $description ) ) {
            return new WP_Error(
                'data_invalid',
                __( 'Description is required.', 'hostinger-ai-theme' ),
                array( 'status' => \WP_Http::BAD_REQUEST )
            );
        }

        $description = sanitize_text_field( $description );

        $data = array(
            'data' => array(
                'color_options' => $this->website_builder->generate_color_options( $description ),
            ),
        );

        $response = new WP_REST_Response( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    public function generate_font_options( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $data = array(
            'data' => array(
                'font_options' => $this->website_builder->generate_font_options(),
            ),
        );

        $response = new WP_REST_Response( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    private function validate_required_fields( array &$parameters ): ?\WP_Error {
        $required_fields = array(
            'description',
            'language',
            'builder_type',
        );

        $errors = array();

        foreach ( $required_fields as $field_key ) {
            if ( empty( $parameters[ $field_key ] ) ) {
                $errors[ $field_key ] = $field_key . ' is missing.';
            } else {
                $parameters[ $field_key ] = sanitize_text_field( $parameters[ $field_key ] );
            }
        }

        if ( ! empty( $errors ) ) {
            return new \WP_Error(
                'data_invalid',
                __( 'Sorry, something wrong with data.', 'hostinger-ai-theme' ),
                array(
                    'status' => \WP_Http::BAD_REQUEST,
                    'errors' => $errors,
                    'error'  => 'Validation failed: ' . implode( ', ', array_keys( $errors ) ) . ' missing',
                )
            );
        }

        return null;
    }

    private function normalize_website_type( array &$parameters ): void {
        if ( ! empty( $parameters['website_type'] ) && is_array( $parameters['website_type'] ) ) {
            $parameters['website_type'] = array_map( 'sanitize_text_field', $parameters['website_type'] );
        } elseif ( ! empty( $parameters['website_type'] ) && is_string( $parameters['website_type'] ) ) {
            $parameters['website_type'] = [ sanitize_text_field( $parameters['website_type'] ) ];
        } else {
            $parameters['website_type'] = [ 'other' ];
        }
    }

    private function handle_affiliate_type( array $parameters ): void {
        if ( ! in_array( 'affiliate-marketing', $parameters['website_type'], true ) ) {
            return;
        }

        update_option( 'hostinger_ai_affiliate', true );
    }

    private function clear_ai_data( array $website_type, string $builder_type ): void {
        $this->website_builder->clear_ai_content();
        $this->website_builder->clear_ai_data( $website_type, $builder_type );
    }

    private function handle_online_store_options( array $parameters ): void {
        if ( ! WebsiteTypeHelper::contains( $parameters['website_type'], 'online store' ) ) {
            return;
        }

        if ( HostingerEcommerceHelper::is_active() ) {
            return;
        }

        update_option( 'hostinger_ai_woo', true );
    }

    private function save_website_options( array $parameters ): void {
        $website_type = array_map( 'strtolower', $parameters['website_type'] );
        update_option( 'hostinger_ai_website_type', $website_type );
        update_option( 'blogname', $parameters['brand_name'] );
        update_option( 'hostinger_ai_brand_name', $parameters['brand_name'] );
        update_option( 'hostinger_ai_description', $parameters['description'] );
        update_option( 'hostinger_ai_builder_type', $parameters['builder_type'] );
    }

    public function enable_plugins( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $results = $this->website_builder->enable_plugins();

        $plugin_step_map = array(
            'elementor'   => 'elementor_installation',
            'woocommerce' => 'woocommerce_installation',
            'affiliate'   => 'affiliate_installation',
            'reach'       => 'reach_installation',
        );

        $has_failures = false;
        $failed_plugins = array();
        foreach ( $results as $plugin => $result ) {
            if ( $result['status'] === 'failed' ) {
                $has_failures = true;
                $failed_plugins[] = $plugin;
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "Hostinger AI Theme: $plugin plugin installation failed - " . $result['error'] );
                }

                $failed_step = $plugin_step_map[ $plugin ] ?? 'plugin_enable';
                $this->send_failure_event(
                    $failed_step,
                    'plugin_installation',
                    'Plugin installation failed: ' . $plugin,
                    $result['error'] ?? null
                );
            }
        }

        $data = array(
            'success' => ! $has_failures,
            'plugins' => $results,
        );

        if ( $has_failures ) {
            $failed_plugins_str = implode( ', ', $failed_plugins );
            $error_message = 'Plugin installation failed: ' . $failed_plugins_str;

            return new WP_Error(
                'plugin_installation_failed',
                __( 'Plugin installation failed.', 'hostinger-ai-theme' ),
                array(
                    'status'  => WP_Http::SERVICE_UNAVAILABLE,
                    'plugins' => $results,
                    'error'   => $error_message,
                )
            );
        }

        $response = new WP_REST_Response( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    public function generate_pages_list( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $brand_name   = $request->get_param( 'brand_name' );
        $description  = $request->get_param( 'description' );
        $website_type = WebsiteTypeHelper::get_website_types();

        if ( empty( $brand_name ) || empty( $description ) ) {
            return new WP_Error(
                'data_invalid',
                __( 'Brand data not found.', 'hostinger-ai-theme' ),
                array( 'status' => WP_Http::BAD_REQUEST )
            );
        }

        try {
            $pages = $this->website_builder->get_pages_list( $brand_name, $website_type, $description );
            $pages = array_values( array_filter( $pages, function ( $page ) {
                return ! in_array( $page['id'], self::EXCLUDED_PAGE_IDS, true );
            } ) );
        } catch ( Exception $e ) {
            return new WP_Error(
                'pages_generation_failed',
                __( 'Failed to generate pages list.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                    'error'  => $e->getMessage(),
                )
            );
        }

        $response = new WP_REST_Response( array( 'data' => array( 'pages' => $pages ) ) );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function save_pages_structure( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $pages = $request->get_param( 'pages' );

        if ( empty( $pages ) || ! is_array( $pages ) ) {
            return new WP_Error(
                'data_invalid',
                __( 'Pages must be a non-empty array.', 'hostinger-ai-theme' ),
                array( 'status' => WP_Http::BAD_REQUEST )
            );
        }

        $sanitized_pages = array_values( array_filter(
            array_map( function ( $page ) {
                return array(
                    'id'   => sanitize_text_field( $page['id'] ?? '' ),
                    'name' => sanitize_text_field( $page['name'] ?? '' ),
                );
            }, $pages ),
            function ( $page ) {
                return ! empty( $page['id'] ) && ! empty( $page['name'] );
            }
        ) );

        if ( empty( $sanitized_pages ) ) {
            return new WP_Error(
                'data_invalid',
                __( 'Pages must be a non-empty array.', 'hostinger-ai-theme' ),
                array( 'status' => WP_Http::BAD_REQUEST )
            );
        }

        update_option( 'hostinger_ai_pages_structure', $sanitized_pages );

        return new WP_REST_Response( array( 'success' => true ), WP_Http::OK );
    }

    public function save_contact_info( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $contacts = $request->get_param( 'contacts' );

        if ( ! is_array( $contacts ) ) {
            $contacts = array();
        }

        $contact = array(
            'email'              => sanitize_email( (string) ( $contacts['email'] ?? '' ) ),
            'address'            => sanitize_textarea_field( (string) ( $contacts['address'] ?? '' ) ),
            'phone'              => sanitize_text_field( (string) ( $contacts['phone_number'] ?? '' ) ),
            'phone_country_code' => sanitize_text_field( (string) ( $contacts['phone_c_c'] ?? '' ) ),
            'whatsapp'           => rest_sanitize_boolean( $contacts['is_whats_app_connected'] ?? false ),
        );

        update_option( 'hostinger_ai_contact', $contact );

        $response = new WP_REST_Response( array( 'success' => true ) );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    public function save_socials( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $socials = $request->get_param( 'socials' );

        if ( ! is_array( $socials ) ) {
            $socials = array();
        }

        $social = array(
            'instagram' => sanitize_text_field( (string) ( $socials['instagram'] ?? '' ) ),
            'facebook'  => sanitize_text_field( (string) ( $socials['facebook'] ?? '' ) ),
            'tiktok'    => sanitize_text_field( (string) ( $socials['tiktok'] ?? '' ) ),
        );

        update_option( 'hostinger_ai_social_links', $social );

        $response = new WP_REST_Response( array( 'success' => true ) );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function generate_structure( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $version = get_option( 'hostinger_ai_version', false );

        if ( empty( $version ) ) {
            $this->send_failure_event( 'structure', 'validation', 'Wrong sequence of step execution - version missing' );

            return new WP_Error(
                'data_invalid',
                __( 'Wrong sequence of step execution.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::BAD_REQUEST,
                    'error'  => 'Wrong sequence of step execution - version missing',
                )
            );
        }

        $brand_name   = get_option( 'hostinger_ai_brand_name' );
        $website_type = WebsiteTypeHelper::get_website_types();
        $description  = get_option( 'hostinger_ai_description' );

        $saved_pages = get_option( 'hostinger_ai_pages_structure', false );
        $pages       = array();
        if ( ! empty( $saved_pages ) && is_array( $saved_pages ) ) {
            $pages = array_column( $saved_pages, 'name' );
        }

        try {
            $structure_generated = $this->website_builder->generate_structure( $brand_name, $website_type, $description, $pages );

            if ( ! $structure_generated ) {
                $this->send_failure_event( 'structure', 'ai_endpoint', 'Structure generation failed - empty response' );

                return new WP_Error(
                    'structure_generation_failed',
                    __( 'Failed to generate structure.', 'hostinger-ai-theme' ),
                    array(
                        'status' => WP_Http::SERVICE_UNAVAILABLE,
                        'error'  => 'Structure generation failed',
                    )
                );
            }

            $data = array(
                'structure_generated' => $structure_generated,
            );
        } catch ( Exception $e ) {
            $this->send_failure_event( 'structure', 'ai_endpoint', $e->getMessage() );

            return new WP_Error(
                'structure_generation_failed',
                __( 'Failed to generate structure.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                    'error'  => $e->getMessage(),
                )
            );
        }

        $response = new WP_REST_Response( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function generate_content( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $website_structure = get_option( 'hostinger_ai_website_structure', false );

        if ( empty( $website_structure ) ) {
            $this->send_failure_event( 'content', 'validation', 'Wrong sequence of step execution - structure missing' );

            return new WP_Error(
                'data_invalid',
                __( 'Wrong sequence of step execution.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::BAD_REQUEST,
                    'error'  => 'Wrong sequence of step execution - structure missing',
                )
            );
        }
        $headers = $request->get_headers();

        if ( ! empty( $headers['x_correlation_id'] ) ) {
            update_option( 'hts_correlation_id', $headers['x_correlation_id'][0] );
        }

        $brand_name   = get_option( 'hostinger_ai_brand_name' );
        $website_type = WebsiteTypeHelper::get_website_types();
        $description  = get_option( 'hostinger_ai_description' );

        try {

            $data = array(
                'content_generated' => $this->website_builder->generate_content( $brand_name, $website_type, $description )
            );

        } catch (Throwable $e) {
            $error_message = $e->getMessage();
            $error_code = 'content_generation_failed';
            $failure_type = 'ai_endpoint';

            // Check if this is a plugin installation failure
            if ( strpos( $error_message, 'plugin installation failed' ) !== false ) {
                $error_code = 'plugin_installation_failed';
                $failure_type = 'plugin_installation';
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Hostinger AI Theme: Plugin installation failed during content generation - ' . $error_message );
                }
            }

            $this->send_failure_event( 'content', $failure_type, $error_message );

            return new WP_Error(
                $error_code,
                __( 'Problem generating content.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                    'error'  => $error_message,
                )
            );
        }

        $response = new WP_REST_Response( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function build_content( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $website_content = get_option( 'hostinger_ai_website_content', false );

        if ( empty( $website_content ) ) {
            $this->send_failure_event( 'buildContent', 'validation', 'Wrong sequence of step execution - content missing' );

            return new WP_Error(
                'data_invalid',
                __( 'Wrong sequence of step execution.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::BAD_REQUEST,
                    'error'  => 'Wrong sequence of step execution - content missing',
                )
            );
        }

        try {

            $data = array(
                'content_built' => $this->website_builder->build_content( $website_content )
            );

        } catch (Exception $e) {
            $this->send_failure_event( 'buildContent', 'build_content', $e->getMessage() );

            return new WP_Error(
                'build_content_failed',
                __( 'Problem building content.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                    'error'  => $e->getMessage(),
                )
            );
        }

        $builder_type = get_option( 'hostinger_ai_builder_type', '' );
        if ( $builder_type === BuilderType::GUTENBERG ) {
            ( new GutenbergPreferences() )->disable_welcome_guide();
        }

        // Purge LiteSpeed cache.
        if ( has_action( 'litespeed_purge_all' ) ) {
            do_action( 'litespeed_purge_all' );
        }

        delete_option( 'rewrite_rules' );

        $website_type_for_amplitude = WebsiteTypeHelper::get_website_types();

        $this->amplitude_manager->sendRequest( Endpoints::AMPLITUDE_ENDPOINT, array(
            'action'       => self::AMPLITUDE_EVENT_CREATED,
            'builder_type' => get_option( 'hostinger_ai_builder_type', '' ),
            'website_type' => implode( ', ', $website_type_for_amplitude ),
            'language' => get_locale(),
        ) );

		update_option( Helper::HOSTINGER_AI_THEME_GENERATED_ONCE_OPTION, true );
        GenerationState::complete();

        ( new VersionManager() )->capture_after_generation();

        $this->activate_litespeed_cache();

        $response = new WP_REST_Response( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function enhance_prompt( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $parameters = $request->get_params();
        $text = $parameters['text']; // Already sanitized and validated by Routes.php

        try {
            $enhanced_text = $this->call_ai_enhancement_service( $text );

            $data = array(
                'data' => array(
                    'improved_prompt' => $enhanced_text
                )
            );

            $response = new WP_REST_Response( $data );
            $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
            $response->set_status( WP_Http::OK );

            return $response;

        } catch ( Exception $e ) {
            return new WP_Error(
                'ai_service_error',
                $e->getMessage(),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                )
            );
        }
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function detect_brand_and_type( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $parameters = $request->get_params();
        $description = $parameters['description']; // Already sanitized and validated by Routes.php
        $is_multiple_types = isset( $parameters['is_multiple_types'] )
            ? filter_var( $parameters['is_multiple_types'], FILTER_VALIDATE_BOOLEAN )
            : true;

        try {
            $response_data = $this->call_detect_brand_and_type_service( $description, $is_multiple_types );
            $this->validate_detect_response( $response_data );

            $website_type = [];
            if ( ! empty( $response_data['websiteType'] ) && is_array( $response_data['websiteType'] ) ) {
                $website_type = $response_data['websiteType'];
            } elseif ( ! empty( $response_data['websiteType'] ) && is_string( $response_data['websiteType'] ) ) {
                $website_type = [ $response_data['websiteType'] ];
            }

            $normalized_website_type = array_map( function ( $t ) {
                return strtolower( sanitize_text_field( $t ) );
            }, $website_type );

            update_option( 'hostinger_ai_website_type', $normalized_website_type );

            $data = array(
                'data' => array(
                    'brandName'   => $response_data['brandName'],
                    'websiteType' => $website_type,
                )
            );

            $response = new WP_REST_Response( $data );
            $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
            $response->set_status( WP_Http::OK );

            return $response;

        } catch ( Exception $e ) {
            return new WP_Error(
                'ai_service_error',
                $e->getMessage(),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                )
            );
        }
    }

    public function set_website_language( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $language = $request->get_param( 'language' );

        update_option( 'hostinger_ai_selected_language', $language );

        $response = new WP_REST_Response( array( 'success' => true ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    public function detect_brand_name( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $parameters  = $request->get_params();
        $description = ! empty( $parameters['description'] ) ? $parameters['description'] : '';

        try {
            $response_data = $this->call_detect_brand_name_service( $description );

            if ( empty( $response_data ) ) {
                throw new Exception( 'Detect brand name service did not return brand names' );
            }

            $data = array(
                'data' => $response_data
            );

            $response = new WP_REST_Response( $data );
            $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
            $response->set_status( WP_Http::OK );

            return $response;

        } catch ( Exception $e ) {
            return new WP_Error(
                'ai_service_error',
                $e->getMessage(),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                )
            );
        }
    }

    public function detect_website_type( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $parameters = $request->get_params();
        $description = ! empty( $parameters['description'] ) ? $parameters['description'] : '';
        $brand_name  = ! empty( $parameters['brand_name'] ) ? $parameters['brand_name'] : '';

        try {
            $response_data = $this->call_detect_website_type_service( $description, $brand_name );

            if ( empty( $response_data['websiteTypes'] ) ) {
                throw new Exception( 'Detect website type service did not return websiteTypes' );
            }

            $website_type = is_array( $response_data['websiteTypes'] )
                ? $response_data['websiteTypes']
                : [ $response_data['websiteTypes'] ];

            $normalized_website_type = array_map( function ( $t ) {
                return strtolower( sanitize_text_field( $t ) );
            }, $website_type );

            update_option( 'hostinger_ai_website_type', $normalized_website_type );

            $data = array(
                'data' => array(
                    'websiteType' => $website_type,
                )
            );

            $response = new WP_REST_Response( $data );
            $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
            $response->set_status( WP_Http::OK );

            return $response;

        } catch ( Exception $e ) {
            return new WP_Error(
                'ai_service_error',
                $e->getMessage(),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                )
            );
        }
    }

    public function set_colors( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $parameters = $request->get_json_params();
        if ( empty( $parameters['colors'] ) ) {
            return new WP_Error(
                'data_invalid',
                __( 'Colors data with color_palette is required.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::BAD_REQUEST,
                )
            );
        }

        try {
            $description = get_option('hostinger_ai_description' );

            $colors_set = $this->website_builder->set_colors( $description, $parameters['colors'] );
            $logo_url   = null;

            if ( $colors_set && BundleSchema::is_active() ) {
                $palette = $this->palette_for_envelope_update( $parameters['colors'] );

                EnvelopeCustomization::update_palette( $palette );

                if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
                    WP_Theme_JSON_Resolver::clean_cached_data();
                }

                if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
                    wp_clean_theme_json_cache();
                }

                $logo_url = $this->logo_adapter->adapt_to_palette( $palette );
            }

            if ( $colors_set ) {
                CssCache::bust_all( 'Hostinger AI Theme colors change' );
            }

            $data = array(
                'colors_set' => $colors_set,
            );

            if ( $logo_url !== null ) {
                $data['logo_url'] = $logo_url;
            }

            $response = new WP_REST_Response( array( 'data' => $data ) );
            $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
            $response->set_status( WP_Http::OK );

            return $response;
        } catch ( Exception $e ) {
            return new WP_Error(
                'set_colors_error',
                __( 'Problem setting colors.', 'hostinger-ai-theme' ),
                array(
                    'status' => \WP_Http::BAD_REQUEST,
                    'error'  => $e->getMessage(),
                )
            );
        }
    }

    private function palette_for_envelope_update( array $requested ): array {
        $applied  = get_option( 'hostinger_ai_colors' );
        $palette  = is_array( $applied ) ? $applied : $requested;
        $original = get_option( BundleSchema::OPTION_ORIGINAL_COLORS, array() );

        if ( ! is_array( $original ) || ( $original['color1'] ?? '' ) === '' ) {
            return $palette;
        }

        foreach ( array( 'color1', 'color2', 'color3' ) as $slot ) {
            if ( strtolower( (string) ( $requested[ $slot ] ?? '' ) ) !== strtolower( (string) ( $original[ $slot ] ?? '' ) ) ) {
                return $palette;
            }
        }

        return $requested;
    }

    private function validate_detect_response( array $response_data ): void {
        if ( empty( $response_data ) ) {
            throw new Exception( 'Detect brand and type service returned empty response' );
        }

        if ( empty( $response_data['brandName'] ) ) {
            throw new Exception( 'Detect brand and type service did not return brandName' );
        }

        if ( empty( $response_data['websiteType'] ) ) {
            throw new Exception( 'Detect brand and type service did not return websiteType' );
        }
    }

    private function call_detect_brand_name_service( string $description ): array {
        try {
            $software_id = $this->get_software_id();
            if ( empty( $software_id ) ) {
                throw new Exception( self::SOFTWARE_ID_NOT_AVAILABLE );
            }

            $response_data = $this->wh_api_client->post(
                ApiRoutes::INSTALLATIONS_BASE . $software_id . '/content/detect/brand-name',
                [ 'description' => $description ]
            );

            if ( empty( $response_data ) ) {
                throw new Exception( 'Detect brand name service returned empty response' );
            }

            return $response_data;

        } catch ( Exception $exception ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Detect Brand Name API Error: ' . $exception->getMessage() );
            }
            throw new Exception( 'Detect brand name service temporarily unavailable: ' . $exception->getMessage() );
        }
    }

    private function call_detect_website_type_service( string $description, string $brand_name ): array {
        try {
            $types = $this->website_type_detector->detect( $description, $brand_name );

            if ( empty( $types ) ) {
                throw new Exception( 'Detect website type service returned empty response' );
            }

            return array( 'websiteTypes' => $types );

        } catch ( Exception $exception ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Detect Website Type API Error: ' . $exception->getMessage() );
            }
            throw new Exception( 'Detect website type service temporarily unavailable: ' . $exception->getMessage() );
        }
    }

    private function enhance_text_content( string $text ): string {
        return $this->call_ai_enhancement_service( $text );
    }

    private function call_ai_enhancement_service( string $text ): string {
        try {
            $request_params = ['text' => $text];
            $response_data = $this->request_client->post( '/v3/wordpress/plugin/builder/prompt-enhance', $request_params );
            $this->validate_enhancement_response( $response_data );

            return $response_data['improved_prompt'];

        } catch ( Exception $exception ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'AI Enhancement API Error: ' . $exception->getMessage() );
            }
            throw new Exception( 'AI enhancement service temporarily unavailable: ' . $exception->getMessage() );
        }
    }

    private function validate_enhancement_response( array $response_data ): void {
        if ( empty( $response_data ) ) {
            throw new Exception( 'AI enhancement service returned empty response' );
        }

        if ( ! isset( $response_data['improved_prompt'] ) || empty( $response_data['improved_prompt'] ) ) {
            throw new Exception( 'AI enhancement service did not return improved prompt' );
        }
    }

    private function call_detect_brand_and_type_service( string $description, bool $is_multiple_types = false ): array {
        try {
            $software_id = $this->get_software_id();
            if ( empty( $software_id ) ) {
                throw new Exception( self::SOFTWARE_ID_NOT_AVAILABLE );
            }

            $request_params = [
                'description' => $description,
                'isMultipleTypes' => $is_multiple_types,
            ];
            $response_data = $this->wh_api_client->post( ApiRoutes::INSTALLATIONS_BASE . $software_id . '/content/detect/brand-and-type', $request_params );

            if ( empty( $response_data ) ) {
                throw new Exception( 'Detect brand and type service returned empty response' );
            }

            return $response_data;

        } catch ( Exception $exception ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Detect Brand and Type API Error: ' . $exception->getMessage() );
            }
            throw new Exception( 'Detect brand and type service temporarily unavailable: ' . $exception->getMessage() );
        }
    }

    /**
     * Send amplitude failure event with context.
     *
     * @param string      $failed_step   The step that failed (e.g., 'colors', 'structure', 'content', 'buildContent', 'plugin_enable')
     * @param string      $failure_type  The category of failure (e.g., 'ai_endpoint', 'plugin_installation', 'validation', 'build_content')
     * @param string      $error_message The error message (will be sanitized)
     * @param string|null $error_details The underlying error reported by the failing subsystem, sent as a separate
     *                                   property (will be sanitized). Omitted from the payload when empty.
     */
    private function send_failure_event( string $failed_step, string $failure_type, string $error_message, ?string $error_details = null ): void {
        $params = array(
            'action'        => self::AMPLITUDE_EVENT_FAILURE,
            'failed_step'   => $failed_step,
            'failure_type'  => $failure_type,
            'error_message' => $this->sanitize_error_message( $error_message ),
            'builder_type'  => get_option( 'hostinger_ai_builder_type', 'unknown' ),
            'website_type'  => get_option( 'hostinger_ai_website_type', 'unknown' ),
            'theme_version' => wp_get_theme()->get( 'Version' ),
        );

        if ( ! empty( $error_details ) ) {
            $params['error_details'] = $this->sanitize_error_message( $error_details );
        }

        $this->amplitude_manager->sendRequest( Endpoints::AMPLITUDE_ENDPOINT, $params );
    }

    /**
     * Sanitize error message for amplitude tracking.
     * Removes sensitive data like file paths and long tokens.
     *
     * @param string $error_message
     *
     * @return string
     */
    private function sanitize_error_message( string $error_message ): string {
        // Remove file paths
        $sanitized = preg_replace( '#(/[a-zA-Z0-9._-]+){3,}#', '[path_redacted]', $error_message );
        // Remove long tokens/hashes (32+ chars)
        $sanitized = preg_replace( '/[a-zA-Z0-9]{32,}/', '[token_redacted]', $sanitized );
        // Truncate to 500 chars
        if ( strlen( $sanitized ) > 500 ) {
            $sanitized = substr( $sanitized, 0, 500 ) . '...';
        }

        return $sanitized;
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function check_scam( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $this->deactivate_litespeed_cache();

        $parameters = $request->get_params();
        $description = $parameters['description'];
        $language = $parameters['language'] ?? '';

        try {
            $response_data = $this->call_scam_detector_service( $description, $language );

            $data = array(
                'data' => array(
                    'isScam' => $response_data['isScam'] ?? false,
                    'scamReason' => $response_data['scamReason'] ?? null,
                )
            );

            $response = new \WP_REST_Response( $data );
            $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
            $response->set_status( \WP_Http::OK );

            return $response;

        } catch ( Exception $e ) {
            return new \WP_Error(
                'ai_service_error',
                $e->getMessage(),
                array(
                    'status' => \WP_Http::SERVICE_UNAVAILABLE,
                )
            );
        }
    }

    public function skip_ai_builder(): WP_REST_Response {
        update_option( 'hostinger_ai_version', time() );

        if ( HostingerEcommerceHelper::is_active() ) {
            $result = ( new HostingerEcommerceBuilder() )->boot();

            if ( is_wp_error( $result ) ) {
                Helper::log( 'Ecommerce plugin install on skip failed: ' . $result->get_error_message() );
            }
        }

        $response = new WP_REST_Response( array( 'success' => true ) );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    private function deactivate_litespeed_cache(): void {
        if ( ! function_exists( 'deactivate_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( is_plugin_active( Helper::LITESPEED_PLUGIN ) ) {
            deactivate_plugins( Helper::LITESPEED_PLUGIN, true );
        }

        $dropin = WP_CONTENT_DIR . '/object-cache.php';
        if ( file_exists( $dropin ) ) {
            global $wp_filesystem;
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            WP_Filesystem();

            if ( $wp_filesystem ) {
                $wp_filesystem->delete( $dropin );
            }
        }
    }

    private function activate_litespeed_cache(): void {
        Helper::activate_litespeed_cache();
    }

    private function call_scam_detector_service( string $description, string $language = '' ): array {
        try {
            $software_id = $this->get_software_id();
            if ( empty( $software_id ) ) {
                throw new Exception( self::SOFTWARE_ID_NOT_AVAILABLE );
            }

            if ( empty( $language ) ) {
                $language = get_option( 'hostinger_ai_selected_language', 'en_US' );
            }

            $language = sanitize_text_field( $language );

            $request_params = [
                'description' => $description,
                'language' => $language,
            ];
            $response_data = $this->wh_api_client->post( ApiRoutes::INSTALLATIONS_BASE . $software_id . '/content/detect/scam', $request_params );

            if ( empty( $response_data ) ) {
                throw new Exception( 'Scam detector service returned empty response' );
            }

            return $response_data;

        } catch ( Exception $exception ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Scam Detector API Error: ' . $exception->getMessage() );
            }
            throw new Exception( 'Scam detector service temporarily unavailable: ' . $exception->getMessage() );
        }
    }
}
