<?php

namespace Hostinger\AiTheme\Shortcodes;

use Hostinger\AiTheme\Data\HostingerEcommerceHelper;
use WP_Block_Type;
use WP_Block_Type_Registry;

defined( 'ABSPATH' ) || exit;

class ShortcodesManager {
    public function __construct() {
        add_action( 'init', array( $this, 'register_shortcodes' ) );
    }

    public function register_shortcodes(): void {
        add_shortcode( 'hostinger_contact_form', array( $this, 'render_contact_form' ) );
        add_shortcode( 'hostinger_booking_form', array( $this, 'render_booking_form' ) );
        add_shortcode( 'hostinger_ecommerce_cart_icon', array( $this, 'render_ecommerce_cart_icon' ) );
    }

    public function render_ecommerce_cart_icon(): string {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path fill="currentColor" fill-rule="evenodd" d="M.998 1.877a1 1 0 1 0 0 2h1.56c.418 0 .784.282.891.687l2.775 10.518a2.92 2.92 0 0 0 2.825 2.176h8.945a1 1 0 1 0 0-2H9.049a.92.92 0 0 1-.891-.686l-.21-.798h9.632c.997 0 1.894-.606 2.266-1.532l1.911-4.765a2.44 2.44 0 0 0-2.265-3.35H5.402l-.02-.074a2.92 2.92 0 0 0-2.824-2.176zm4.932 4.25 1.49 5.647h10.16c.18 0 .342-.11.41-.277L19.9 6.733a.44.44 0 0 0-.41-.606zm12.151 14.27a1.725 1.725 0 1 1-3.45 0 1.725 1.725 0 0 1 3.45 0m-8.93 1.726a1.725 1.725 0 1 0 0-3.45 1.725 1.725 0 0 0 0 3.45" clip-rule="evenodd"></path></svg>';

        return sprintf(
            '<a class="hostinger-ai-cart-icon" href="%s" aria-label="%s" style="color:var(--wp--preset--color--page-text);display:inline-flex;align-items:center;">%s</a>',
            esc_url( HostingerEcommerceHelper::get_cart_url() ),
            esc_attr__( 'Cart', 'hostinger-ai-theme' ),
            $svg
        );
    }

    public function render_contact_form( array $atts ): string {
        $atts = shortcode_atts(
            array(
                'title'               => '',
                'description'         => '',
                'show_title'          => 'true',
                'show_description'    => 'true',
                'button_text'         => __( 'Send Message', 'hostinger-ai-theme' ),
                'name_label'          => __( 'Name', 'hostinger-ai-theme' ),
                'email_label'         => __( 'Email', 'hostinger-ai-theme' ),
                'message_label'       => __( 'Message', 'hostinger-ai-theme' ),
                'name_placeholder'    => __( "What's your name?", 'hostinger-ai-theme' ),
                'email_placeholder'   => __( "What's your email?", 'hostinger-ai-theme' ),
                'message_placeholder' => __( 'Write your message...', 'hostinger-ai-theme' ),
                'privacy_policy_text' => '',
            ),
            $atts
        );

        $attributes = array(
            'title'              => $atts['title'],
            'description'        => $atts['description'],
            'showTitle'          => $atts['show_title'] === 'true',
            'showDescription'    => $atts['show_description'] === 'true',
            'buttonText'         => $atts['button_text'],
            'nameLabel'          => $atts['name_label'],
            'emailLabel'         => $atts['email_label'],
            'messageLabel'       => $atts['message_label'],
            'namePlaceholder'    => $atts['name_placeholder'],
            'emailPlaceholder'   => $atts['email_placeholder'],
            'messagePlaceholder' => $atts['message_placeholder'],
            'privacyPolicyText'  => $atts['privacy_policy_text'],
        );

        wp_enqueue_script( 'hostinger-contact-form-block' );
        $this->enqueue_contact_form_block_styles();

        ob_start();
        include get_template_directory() . '/gutenberg-blocks/ContactForm/render.php';
        return ob_get_clean();
    }

    public function render_booking_form(): string {
        return do_blocks( '<!-- wp:hostinger-ai-theme/booking-block /-->' );
    }

    private function enqueue_contact_form_block_styles(): void {
        $block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'hostinger-ai-theme/contact-form-block' );

        if ( ! $block_type instanceof WP_Block_Type ) {
            return;
        }

        foreach ( $block_type->style_handles as $style_handle ) {
            wp_enqueue_style( $style_handle );
        }
    }
}
