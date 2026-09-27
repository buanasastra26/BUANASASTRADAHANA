<?php

namespace Hostinger\AiTheme\Elementor;

use Hostinger\AiTheme\Elementor\Widgets\ContactFormWidget;
use Hostinger\AiTheme\Elementor\Widgets\BlogPostsWidget;
use \Elementor\Widget_Base;
use \Elementor\Widgets_Manager;

defined( 'ABSPATH' ) || exit;

class WidgetManager {

    private array $widgets = [
        ContactFormWidget::class,
        BlogPostsWidget::class,
    ];

    public function __construct() {
        add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
        add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_widget_scripts' ) );
        add_action( 'wp_ajax_submit_elementor_contactform', array( ContactFormWidget::class, 'handle_contact_submit' ) );
        add_action( 'wp_ajax_nopriv_submit_elementor_contactform', array( ContactFormWidget::class, 'handle_contact_submit' ) );
    }

    public function register_widgets( \Elementor\Widgets_Manager $widgets_manager ): void {
        foreach ( $this->widgets as $widget_class ) {
            if ( ! class_exists( $widget_class ) ) {
                continue;
            }

            $widget_instance = new $widget_class();

            if ( $widget_instance instanceof \Elementor\Widget_Base ) {
                $widgets_manager->register( $widget_instance );
            }
        }
    }

    public function register_widget_scripts(): void {
        wp_register_script(
            'hostinger-elementor-widgets',
            get_template_directory_uri() . '/assets/js/elementor-widgets.min.js',
            array( 'jquery', 'elementor-frontend' ),
            wp_get_theme()->get( 'Version' ),
            true
        );

        wp_localize_script(
            'hostinger-elementor-widgets',
            'hostingerElementorContactForm',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'hts_submit_contactform' ),
                'error'    => __( 'An error occurred. Please try again later.', 'hostinger-ai-theme' ),
            )
        );
    }
}
