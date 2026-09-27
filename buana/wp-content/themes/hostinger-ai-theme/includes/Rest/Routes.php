<?php

namespace Hostinger\AiTheme\Rest;

/**
 * Avoid possibility to get file accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Class for handling Rest Api Routes
 */
class Routes {
    /**
     * @var Settings
     */
    private BuilderRoutes $builder_routes;
    private BlockTypeRoutes $block_type_routes;
    private LogoRoutes $logo_routes;
    private VersionRoutes $version_routes;

    /**
     * @param BuilderRoutes $builder_routes
     * @param BlockTypeRoutes $block_type_routes
     * @param LogoRoutes $logo_routes
     * @param VersionRoutes|null $version_routes
     */
    public function __construct( BuilderRoutes $builder_routes, BlockTypeRoutes $block_type_routes, LogoRoutes $logo_routes, ?VersionRoutes $version_routes = null ) {
        $this->builder_routes = $builder_routes;
        $this->block_type_routes = $block_type_routes;
        $this->logo_routes = $logo_routes;
        $this->version_routes = $version_routes ?? new VersionRoutes();
    }

    /**
     * Init rest routes
     *
     * @return void
     */
    public function init(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * @return void
     */
    public function register_routes() {
        // Register Builder Rest API Routes.
        $this->register_build_routes();

        // Register AI website version Rest API Routes.
        $this->register_version_routes();
    }

    /**
     * Register website version routes
     *
     * @return void
     */
    private function register_version_routes(): void {
        $id_arg = array(
            'id' => array(
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => fn( $param ) => $this->validate_positive_int( $param ),
            ),
        );

        // List versions.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'versions',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->version_routes, 'get_versions' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        // Get one version.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'versions/(?P<id>\d+)',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->version_routes, 'get_version' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => $id_arg,
            )
        );

        // Restore a version.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'versions/(?P<id>\d+)/restore',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->version_routes, 'restore_version' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => $id_arg,
            )
        );

        // Delete a version.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'versions/(?P<id>\d+)',
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this->version_routes, 'delete_version' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => $id_arg,
            )
        );

        // Ask the screenshot service to crawl the live site for a version.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'versions/(?P<id>\d+)/screenshot',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->version_routes, 'request_screenshot' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => $id_arg,
            )
        );

        // Poll for a version's screenshot.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'versions/(?P<id>\d+)/screenshot',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->version_routes, 'get_screenshot' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => $id_arg,
            )
        );
    }

    /**
     * Register build routes
     *
     * @return void
     */
    private function register_build_routes(): void {
        // Set fonts.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'set-fonts',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->builder_routes, 'set_fonts' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'heading_font' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'body_font'    => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ),
                ),
            )
        );

        // Generate colors.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'generate-colors',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->builder_routes, 'generate_colors' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        // Generate color options.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'generate-color-options',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->builder_routes, 'generate_color_options' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        // Generate font options.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'generate-font-options',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->builder_routes, 'generate_font_options' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        // Set colors.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'set-colors',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->builder_routes, 'set_colors' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        // Enable plugins.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'enable-plugins',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->builder_routes, 'enable_plugins' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'location' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ),
                ),
            )
        );

        // Generate pages list.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'generate-pages-list',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->builder_routes, 'generate_pages_list' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'brand_name'  => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'description' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        // Save pages structure.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'save-pages-structure',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->builder_routes, 'save_pages_structure' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'pages' => array(
                        'required'          => true,
                        'type'              => 'array',
                        'validate_callback' => fn( $param ) => $this->validate_non_empty_array( $param ),
                    ),
                ),
            )
        );

        // Save contact info.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'save-contact-info',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->builder_routes, 'save_contact_info' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'contacts' => array(
                        'required' => true,
                        'type'     => 'object',
                    ),
                ),
            )
        );

        // Save socials.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'save-socials',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->builder_routes, 'save_socials' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'socials' => array(
                        'required' => true,
                        'type'     => 'object',
                    ),
                ),
            )
        );

        // Generate structure.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'generate-structure',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->builder_routes, 'generate_structure' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        // Generate content.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'generate-content',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->builder_routes, 'generate_content' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        // Build content.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'build-content',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this->builder_routes, 'build_content' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        // Enhance prompt.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'prompt-enhance',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->builder_routes, 'enhance_prompt' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'text' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'validate_callback' => fn( $param ) => $this->validate_non_empty_string( $param ),
                    ),
                ),
            )
        );

        // Detect brand name and website type.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'detect-brand-and-type',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->builder_routes, 'detect_brand_and_type' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'description' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => fn( $param ) => $this->validate_non_empty_string( $param, 11 ),
                    ),
                    'is_multiple_types' => array(
                        'required'          => false,
                        'type'              => 'boolean',
                        'sanitize_callback' => function( $param ) {
                            return filter_var( $param, FILTER_VALIDATE_BOOLEAN );
                        },
                        'default'           => true,
                    ),
                ),
            )
        );

        // Save the selected website language.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'set-website-language',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->builder_routes, 'set_website_language' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'language' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'detect-brand-name',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->builder_routes, 'detect_brand_name' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'description' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => fn( $param ) => $this->validate_non_empty_string( $param, 11 ),
                    ),
                ),
            )
        );

        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'detect-website-type',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->builder_routes, 'detect_website_type' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'description' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => fn( $param ) => $this->validate_non_empty_string( $param, 11 ),
                    ),
                    'brand_name'  => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        // Check for scam content.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'check-scam',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->builder_routes, 'check_scam' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'description' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'validate_callback' => fn( $param ) => $this->validate_non_empty_string( $param ),
                    ),
                    'language' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        // Determine block type and generate section.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'generate-section',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->block_type_routes, 'generate_section' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'description' => array(
                        'required' => true,
                        'type'     => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        // Generate full page with sections.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'generate-page',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->block_type_routes, 'generate_page' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'description' => array(
                        'required' => true,
                        'type'     => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'page_name' => array(
                        'required' => true,
                        'type'     => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        // Generate content only.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'generate-text-content',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->block_type_routes, 'generate_content' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'description' => array(
                        'required' => true,
                        'type'     => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'tone_of_voice' => array(
                        'required' => true,
                        'type'     => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'content_length' => array(
                        'required' => true,
                        'type'     => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'post_type' => array(
                        'required' => true,
                        'type'     => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'add_images' => array(
                        'required' => false,
                        'type'     => 'boolean',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        // Skip AI builder (allows redirecting to WP admin).
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'skip-ai-builder',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->builder_routes, 'skip_ai_builder' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        // Set logo.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'set-logo',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->logo_routes, 'set_logo' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'attachment_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => fn( $param ) => $this->validate_positive_int( $param ),
                    ),
                ),
            )
        );

        // Remove logo.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'remove-logo',
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this->logo_routes, 'remove_logo' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        // Restore generated logo.
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'restore-generated-logo',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this->logo_routes, 'restore_generated_logo' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );
    }

    public function validate_non_empty_string( $param, int $min = 1, int $max = 2000 ): bool {
        if ( ! is_string( $param ) ) {
            return false;
        }

        $length = mb_strlen( trim( $param ), 'UTF-8' );
        return $length >= $min && $length <= $max;
    }

    public function validate_positive_int( $param ): bool {
        return is_numeric( $param ) && (int) $param > 0;
    }

    public function validate_non_empty_array( $param ): bool {
        return is_array( $param ) && ! empty( $param );
    }

    /**
     * @param WP_REST_Request $request WordPress rest request.
     *
     * @return bool
     */
    public function permission_check( $request ): bool {
        // Workaround if Rest Api endpoint cache is enabled.
        // We don't want to cache these requests.
        if( has_action('litespeed_control_set_nocache') ) {
            do_action(
                'litespeed_control_set_nocache',
                'Custom Rest API endpoint, not cacheable.'
            );
        }

        if ( empty( is_user_logged_in() ) ) {
            return false;
        }

        // Implement custom capabilities when needed.
        return current_user_can( 'manage_options' );
    }
}
