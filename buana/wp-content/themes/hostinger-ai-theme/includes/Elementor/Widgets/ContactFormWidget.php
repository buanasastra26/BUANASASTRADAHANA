<?php

namespace Hostinger\AiTheme\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class ContactFormWidget extends Widget_Base {

    public function get_name(): string {
        return 'hostinger-contact-form';
    }

    public function get_title(): string {
        return __( 'Contact Form', 'hostinger-ai-theme' );
    }

    public function get_icon(): string {
        return 'eicon-form-horizontal';
    }

    public function get_categories(): array {
        return [ 'general' ];
    }

    public function get_keywords(): array {
        return [ 'contact', 'form', 'email', 'message' ];
    }

    public function get_script_depends(): array {
        return [ 'hostinger-elementor-widgets' ];
    }

    public function get_style_depends(): array {
        return [ 'hostinger-elementor-contact-form-styles' ];
    }

    protected function register_controls(): void {
        $this->register_content_controls();
        $this->register_form_fields_controls();
        $this->register_privacy_policy_controls();
    }

    private function register_content_controls(): void {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Content', 'hostinger-ai-theme' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label'        => __( 'Show Title', 'hostinger-ai-theme' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Show', 'hostinger-ai-theme' ),
                'label_off'    => __( 'Hide', 'hostinger-ai-theme' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'title',
            [
                'label'       => __( 'Title', 'hostinger-ai-theme' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => __( 'Get in Touch', 'hostinger-ai-theme' ),
                'placeholder' => __( 'Enter form title', 'hostinger-ai-theme' ),
                'condition'   => [
                    'show_title' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_description',
            [
                'label'        => __( 'Show Description', 'hostinger-ai-theme' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Show', 'hostinger-ai-theme' ),
                'label_off'    => __( 'Hide', 'hostinger-ai-theme' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_date',
            [
                'label'        => __( 'Show Date', 'hostinger-ai-theme' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Show', 'hostinger-ai-theme' ),
                'label_off'    => __( 'Hide', 'hostinger-ai-theme' ),
                'return_value' => 'yes',
                'default'      => 'no',
            ]
        );

        $this->add_control(
            'description',
            [
                'label'       => __( 'Description', 'hostinger-ai-theme' ),
                'type'        => Controls_Manager::TEXTAREA,
                'default'     => __( 'We\'d love to hear from you. Send us a message and we\'ll respond as soon as possible.', 'hostinger-ai-theme' ),
                'placeholder' => __( 'Enter form description', 'hostinger-ai-theme' ),
                'condition'   => [
                    'show_description' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label'       => __( 'Button Text', 'hostinger-ai-theme' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => __( 'Send Message', 'hostinger-ai-theme' ),
                'placeholder' => __( 'Enter button text', 'hostinger-ai-theme' ),
            ]
        );

        $this->add_control(
            'recipient_email',
            [
                'label'       => __( 'Recipient Email Address', 'hostinger-ai-theme' ),
                'type'        => Controls_Manager::TEXT,
                'input_type'  => 'email',
                'default'     => get_option( 'admin_email' ),
                'placeholder' => __( 'Enter recipient email address', 'hostinger-ai-theme' ),
                'description' => __( 'Email address where form submissions will be sent. Defaults to WordPress admin email.', 'hostinger-ai-theme' ),
            ]
        );

        $this->end_controls_section();
    }

    private function register_form_fields_controls(): void {
        $this->start_controls_section(
            'form_fields_section',
            [
                'label' => __( 'Form Fields', 'hostinger-ai-theme' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'name_label',
            [
                'label'   => __( 'Name Label', 'hostinger-ai-theme' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Name', 'hostinger-ai-theme' ),
            ]
        );

        $this->add_control(
            'name_placeholder',
            [
                'label'   => __( 'Name Placeholder', 'hostinger-ai-theme' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'What\'s your name?', 'hostinger-ai-theme' ),
            ]
        );

        $this->add_control(
            'email_label',
            [
                'label'   => __( 'Email Label', 'hostinger-ai-theme' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Email', 'hostinger-ai-theme' ),
            ]
        );

        $this->add_control(
            'email_placeholder',
            [
                'label'   => __( 'Email Placeholder', 'hostinger-ai-theme' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'What\'s your email?', 'hostinger-ai-theme' ),
            ]
        );

        $this->add_control(
            'message_label',
            [
                'label'   => __( 'Message Label', 'hostinger-ai-theme' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Message', 'hostinger-ai-theme' ),
            ]
        );

        $this->add_control(
            'message_placeholder',
            [
                'label'   => __( 'Message Placeholder', 'hostinger-ai-theme' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Write your message...', 'hostinger-ai-theme' ),
            ]
        );

        $this->add_control(
            'date_label',
            [
                'label'     => __( 'Date Label', 'hostinger-ai-theme' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'Date', 'hostinger-ai-theme' ),
                'condition' => [
                    'show_date' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'date_placeholder',
            [
                'label'     => __( 'Date Placeholder', 'hostinger-ai-theme' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'Select a date', 'hostinger-ai-theme' ),
                'condition' => [
                    'show_date' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();
    }

    private function register_privacy_policy_controls(): void {
        $this->start_controls_section(
            'privacy_policy_section',
            [
                'label' => __( 'Privacy Policy', 'hostinger-ai-theme' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'privacy_policy_text',
            [
                'label'       => __( 'Privacy Policy Text', 'hostinger-ai-theme' ),
                'type'        => Controls_Manager::TEXTAREA,
                'default'     => '',
                'placeholder' => __( 'Leave empty to use default privacy policy text', 'hostinger-ai-theme' ),
                'description' => __( 'Leave empty to use the default privacy policy text with automatic link generation.', 'hostinger-ai-theme' ),
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $form_id  = 'contact-form-' . uniqid();

        $privacy_policy_text = $settings['privacy_policy_text'];
        if ( empty( $privacy_policy_text ) ) {
            $privacy_policy_text = sprintf(
                '%s %s%s%s %s',
                __( 'I consent to use of provided personal data for the purpose of responding to the request as described in', 'hostinger-ai-theme' ),
                '<a href="' . esc_url( get_privacy_policy_url() ) . '" target="_blank">',
                __( 'Privacy Policy', 'hostinger-ai-theme' ),
                '</a>',
                __( 'which I have read. I may withdraw my consent at any time.', 'hostinger-ai-theme' )
            );
        }

        wp_enqueue_script( 'hostinger-contact-form-block' );
        ?>
        <div class="hostinger-elementor-contact-form">
            <section class="hts-section hts-page hts-contact-form">
                <div class="hts-details">
                    <div class="elementor-hts-contact-details elementor-hts-contacts">
                        <?php if ( 'yes' === $settings['show_title'] && ! empty( $settings['title'] ) ) : ?>
                            <h2 class="contact-form-title"><?php echo esc_html( $settings['title'] ); ?></h2>
                        <?php endif; ?>

                        <?php if ( 'yes' === $settings['show_description'] && ! empty( $settings['description'] ) ) : ?>
                            <p class="contact-form-description"><?php echo esc_html( $settings['description'] ); ?></p>
                        <?php endif; ?>

                        <form id="<?php echo esc_attr( $form_id ); ?>" data-recipient="<?php echo esc_attr( base64_encode( $settings['recipient_email'] ) ); ?>">
                            <?php wp_nonce_field( 'hts_submit_contactform', 'contactform_nonce' ); ?>

                            <label for="<?php echo esc_attr( $form_id ); ?>-name"><?php echo esc_html( $settings['name_label'] ); ?></label>
                            <input type="text"
                                   id="<?php echo esc_attr( $form_id ); ?>-name"
                                   class="contact-name"
                                   name="name"
                                   placeholder="<?php echo esc_attr( $settings['name_placeholder'] ); ?>"
                                   required>

                            <label for="<?php echo esc_attr( $form_id ); ?>-email"><?php echo esc_html( $settings['email_label'] ); ?></label>
                            <input type="email"
                                   id="<?php echo esc_attr( $form_id ); ?>-email"
                                   class="contact-email"
                                   name="email"
                                   placeholder="<?php echo esc_attr( $settings['email_placeholder'] ); ?>"
                                   required>

                            <?php if ( 'yes' === $settings['show_date'] ) : ?>
                                <label for="<?php echo esc_attr( $form_id ); ?>-date"><?php echo esc_html( $settings['date_label'] ); ?></label>
                                <input type="date"
                                       id="<?php echo esc_attr( $form_id ); ?>-date"
                                       class="contact-date"
                                       name="date"
                                       placeholder="<?php echo esc_attr( $settings['date_placeholder'] ); ?>"
                                       required>
                            <?php endif; ?>

                            <label for="<?php echo esc_attr( $form_id ); ?>-message"><?php echo esc_html( $settings['message_label'] ); ?></label>
                            <textarea id="<?php echo esc_attr( $form_id ); ?>-message"
                                      class="contact-message"
                                      name="message"
                                      placeholder="<?php echo esc_attr( $settings['message_placeholder'] ); ?>"
                                      required></textarea>

                            <div class="hts-privacy-agree">
                                <label class="hts-form-control">
                                    <input type="checkbox"
                                           id="<?php echo esc_attr( $form_id ); ?>-privacy-policy-checkbox"
                                           class="privacy-policy-checkbox"
                                           name="privacy_policy"
                                           required>
                                    <span><?php echo wp_kses_post( $privacy_policy_text ); ?></span>
                                </label>
                            </div>

                            <input type="submit"
                                   class="btn wp-element-button"
                                   value="<?php echo esc_attr( $settings['button_text'] ); ?>">
                            <div class="validate-message"></div>
                        </form>
                    </div>
                </div>
            </section>
        </div>
        <?php
    }

    public static function handle_contact_submit(): void {
        check_ajax_referer( 'hts_submit_contactform', 'nonce' );

        $name             = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
        $email            = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        $date             = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
        $privacy_policy   = isset( $_POST['privacy_policy'] ) ? sanitize_text_field( $_POST['privacy_policy'] ) : '';
        $form_message     = isset( $_POST['message'] ) ? sanitize_text_field( $_POST['message'] ) : '';
        $recipient_email  = isset( $_POST['recipient_email'] ) ? sanitize_email( $_POST['recipient_email'] ) : '';

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
            'date'         => $date,
            'form_message' => $form_message,
        );

        $message = self::get_email_content( $email_data );

        $headers = array(
            'From: ' . get_bloginfo( 'name' ) . ' <info@' . parse_url( home_url(), PHP_URL_HOST ) . '>',
            'Reply-To: ' . $name . ' <' . $email . '>',
            'Content-Type: text/plain; charset=UTF-8',
        );

        $admin_email = get_option( 'admin_email' );
        $send_to     = ! empty( $recipient_email ) && is_email( $recipient_email ) ? $recipient_email : $admin_email;

        if ( is_email( $send_to ) && wp_mail( $send_to, $subject, $message, $headers ) ) {
            wp_send_json_success( array( 'message' => __( 'Successfully submitted!', 'hostinger-ai-theme' ) ) );
        } else {
            $error_message = __( 'Failed to send email. Please try again later.', 'hostinger-ai-theme' );
            wp_send_json_error( array( 'message' => $error_message ) );
        }
    }

    private static function get_email_content( array $email_data ): string {
        ob_start();

        get_template_part( 'gutenberg-blocks/ContactForm/templates/email', 'content', $email_data );

        return ob_get_clean();
    }
}

