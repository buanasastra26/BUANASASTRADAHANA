<?php

namespace Hostinger\AiTheme\GutenbergBlocks\ContactForm;

defined( 'ABSPATH' ) || exit;

class ContactForm {
    public function __construct() {
        add_action( 'init', array( $this, 'register_block' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets_for_editor' ) );
        add_action( 'wp_ajax_submit_contactform', array( $this, 'handle_contact_submit' ) );
        add_action( 'wp_ajax_nopriv_submit_contactform', array( $this, 'handle_contact_submit' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
    }

    public function register_block(): void {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type(
            __DIR__ . '/block.json',
            array(
                'render_callback' => array( $this, 'render_block' ),
            )
        );
    }

    public function enqueue_assets_for_editor(): void {
        if ( ! is_admin() ) {
            return;
        }

        wp_register_script(
            'hostinger-contact-form-block-editor-script',
            get_template_directory_uri() . '/gutenberg-blocks/ContactForm/build/index.js',
            array(
                'wp-blocks',
                'wp-element',
                'wp-editor',
                'wp-i18n',
                'wp-components',
                'wp-server-side-render',
            ),
            wp_get_theme()->get( 'Version' ),
            true
        );

        wp_enqueue_script( 'hostinger-contact-form-block-editor-script' );

        wp_add_inline_script(
            'hostinger-contact-form-block-editor-script',
            'window.hst_contact_form_block_data = ' . wp_json_encode(
                array(
                    'user_id'            => get_current_user_id(),
                    'privacy_policy_url' => get_privacy_policy_url(),
                )
            ) . ';',
            'before'
        );
    }

    public function register_scripts(): void {
        wp_register_script(
            'hostinger-contact-form-block',
            get_template_directory_uri() . '/gutenberg-blocks/ContactForm/build/view.js',
            array( 'jquery' ),
            wp_get_theme()->get( 'Version' ),
            true
        );

        wp_localize_script(
            'hostinger-contact-form-block',
            'hostinger_contact_form',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'submit_contactform' ),
                'error'    => __( 'An error occurred. Please try again later.', 'hostinger-ai-theme' ),
            )
        );
    }

    public function render_block( array $attributes, string $content, \WP_Block $block ): string {
        wp_enqueue_script( 'hostinger-contact-form-block' );

        ob_start();
        require __DIR__ . '/render.php';

        return ob_get_clean();
    }

    public function handle_contact_submit(): void {
        check_ajax_referer( 'submit_contactform', 'nonce' );

        $name           = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
        $email          = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        $privacy_policy = isset( $_POST['privacy_policy'] ) ? sanitize_text_field( $_POST['privacy_policy'] ) : '';
        $form_message   = isset( $_POST['message'] ) ? sanitize_text_field( $_POST['message'] ) : '';

        if ( $privacy_policy !== 'on' ) {
            wp_send_json_error( array( 'message' => __( 'Please agree with privacy policy.', 'hostinger-ai-theme' ) ) );
        }

        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'hostinger-ai-theme' ) ) );
        }

        $subject = __( 'New Contact Form Submission', 'hostinger-ai-theme' );

        $email_data = array(
            'name'         => $name,
            'email'        => $email,
            'form_message' => $form_message,
        );

        $message = $this->get_email_content( $email_data );

        $headers = array(
            'From: ' . get_bloginfo( 'name' ) . ' <info@' . parse_url( home_url(), PHP_URL_HOST ) . '>',
            'Reply-To: ' . $name . ' <' . $email . '>',
            'Content-Type: text/plain; charset=UTF-8',
        );

        $admin_email = get_option( 'admin_email' );
        $send_to     = $admin_email;

	    do_action(
		    'hostinger_reach_submit',
		    array(
			    'group'    => 'WordPress',
				'name'     => $name,
			    'email'    => $email,
			    'metadata' => array(
				    'plugin'  => 'ai-theme',
				    'form_id' => 'ai-theme',
			    ),
		    )
	    );

        if ( is_email( $send_to ) && wp_mail( $send_to, $subject, $message, $headers ) ) {
            wp_send_json_success( array( 'message' => __( 'Successfully submitted!', 'hostinger-ai-theme' ) ) );
        } else {
            $error_message = __( 'Failed to send email. Please try again later.', 'hostinger-ai-theme' );
            wp_send_json_error( array( 'message' => $error_message ) );
        }
    }

    private function get_email_content( array $email_data ): string {
        ob_start();

        get_template_part( 'gutenberg-blocks/ContactForm/templates/email', 'content', $email_data );

        return ob_get_clean();
    }
}
