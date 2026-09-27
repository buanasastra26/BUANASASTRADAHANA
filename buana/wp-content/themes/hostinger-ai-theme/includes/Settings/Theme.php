<?php

namespace Hostinger\AiTheme\Settings;

use Hostinger\AiTheme\Constants\BuilderType;

defined( 'ABSPATH' ) || exit;

class Theme {
    /**
     * Holds the values to be used in the fields callbacks
     */
    private array $options;

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_theme_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_head', [ $this, 'maybe_hide_header' ] );
        add_filter( 'body_class', [ $this, 'add_builder_body_class' ] );
    }

    /**
     * Add options page to the admin sidebar
     *
     * @return void
     */
    public function add_theme_settings_page(): void {
        add_menu_page(
            __( 'Theme Settings', 'hostinger-ai-theme' ),
            __( 'Theme Settings', 'hostinger-ai-theme' ),
            'manage_options',
            'theme-settings',
            [ $this, 'create_settings_page' ],
            'dashicons-admin-customizer',
            30
        );
    }

    /**
     * Register settings
     *
     * @return void
     */
    public function register_settings(): void {
        register_setting(
            'theme_settings_group',
            'hostinger_ai_theme_display_options',
            [ $this, 'sanitize' ]
        );

        add_settings_section(
            'theme_display_section',
            __( 'Display Settings', 'hostinger-ai-theme' ),
            [ $this, 'print_section_info' ],
            'theme-settings'
        );

        add_settings_field(
            'hide_header',
            __( 'Hide Header', 'hostinger-ai-theme' ),
            [ $this, 'hide_header_callback' ],
            'theme-settings',
            'theme_display_section'
        );
    }

    /**
     * Sanitize each setting field
     *
     * @param mixed $input Contains all settings fields as array keys
     *
     * @return array
     */
    public function sanitize( $input ): array {
        $new_input = [];

        if ( $input === null ) {
            return $new_input;
        }

        if ( isset( $input['hide_header'] ) ) {
            $new_input['hide_header'] = absint( $input['hide_header'] );
        }

        return $new_input;
    }

    /**
     * Print the Section text
     *
     * @return void
     */
    public function print_section_info(): void {
        echo esc_html__( 'Adjust how elements are displayed on your website:', 'hostinger-ai-theme' );
    }

    /**
     * Get the settings option array and print one of its values
     *
     * @return void
     */
    public function hide_header_callback(): void {
        $options = get_option( 'hostinger_ai_theme_display_options', [] );
        $value   = isset( $options['hide_header'] ) ? $options['hide_header'] : 0;

        printf(
            '<input type="checkbox" id="hide_header" name="hostinger_ai_theme_display_options[hide_header]" value="1" %s />',
            checked( 1, $value, false )
        );
        echo '<label for="hide_header"> ' . esc_html__( 'Check this box to hide the header element on all pages', 'hostinger-ai-theme' ) . '</label>';
    }

    /**
     * Options page callback
     *
     * @return void
     */
    public function create_settings_page(): void {
        $this->options = get_option( 'hostinger_ai_theme_display_options', [] );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Theme Settings', 'hostinger-ai-theme' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'theme_settings_group' );
                do_settings_sections( 'theme-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function add_builder_body_class( array $classes ): array {
        $builder_type = get_option( 'hostinger_ai_builder_type', BuilderType::GUTENBERG );

        if ( $builder_type === BuilderType::GUTENBERG ) {
            $classes[] = 'hostinger-ai-builder-gutenberg';
        }

        if ( $builder_type === BuilderType::ELEMENTOR ) {
            $classes[] = 'hostinger-ai-builder-elementor';
        }

        if ( class_exists( 'WooCommerce' ) ) {
            $classes[] = 'hostinger-ai-woocommerce-active';
        }

        return $classes;
    }

    /**
     * Add CSS to hide header if option is checked
     *
     * @return void
     */
    public function maybe_hide_header(): void {
        $options = get_option( 'hostinger_ai_theme_display_options', [] );

        if ( isset( $options['hide_header'] ) && $options['hide_header'] == 1 ) {
            echo '<style type="text/css">

                body.hostinger-ai-builder-elementor {
                    padding-top: 0;
                }

                header, .header, #header, .site-header {
                    display: none !important;
                }
            </style>';
        }
    }
}
