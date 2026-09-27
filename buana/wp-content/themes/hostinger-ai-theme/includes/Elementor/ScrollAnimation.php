<?php

namespace Hostinger\AiTheme\Elementor;

use Elementor\Controls_Manager;
use Elementor\Element_Base;

defined( 'ABSPATH' ) || exit;

class ScrollAnimation {

    private const CONTROL_ID = 'hostinger_scroll_animation';
    private const SECTION_ID = 'hostinger_scroll_animation_section';

    public function __construct() {
        add_action( 'elementor/element/section/section_advanced/after_section_end', array( $this, 'register_controls' ), 10, 2 );
        add_action( 'elementor/element/container/section_layout/after_section_end', array( $this, 'register_controls' ), 10, 2 );
        add_action( 'elementor/frontend/section/before_render', array( $this, 'before_render' ) );
        add_action( 'elementor/frontend/container/before_render', array( $this, 'before_render' ) );
        add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'add_initial_styles' ) );
    }

    public function register_controls( Element_Base $element, array $args ): void {
        $element->start_controls_section(
            self::SECTION_ID,
            [
                'tab'   => Controls_Manager::TAB_ADVANCED,
                'label' => esc_html__( 'Scroll Animation', 'hostinger-ai-theme' ),
            ]
        );

        $element->add_control(
            self::CONTROL_ID,
            [
                'type'         => Controls_Manager::SWITCHER,
                'label'        => esc_html__( 'Enable Scroll Animation', 'hostinger-ai-theme' ),
                'description'  => esc_html__( 'Enable fade-up animation when this section scrolls into view.', 'hostinger-ai-theme' ),
                'label_on'     => esc_html__( 'Yes', 'hostinger-ai-theme' ),
                'label_off'    => esc_html__( 'No', 'hostinger-ai-theme' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $element->end_controls_section();
    }

    public function before_render( Element_Base $element ): void {
        $settings = $element->get_settings_for_display();
        $is_enabled = $settings[ self::CONTROL_ID ] ?? 'yes';

        if ( 'yes' === $is_enabled ) {
            $element->add_render_attribute( '_wrapper', [
                'data-aos' => 'fade-up',
                'class'    => 'hostinger-elementor-aos',
            ] );
        }
    }

    public function add_initial_styles(): void {
        $css = '.hostinger-elementor-aos { opacity: 0; } .hostinger-elementor-aos.aos-animate { opacity: 1; }';
        wp_add_inline_style( 'elementor-frontend', $css );
    }
}

