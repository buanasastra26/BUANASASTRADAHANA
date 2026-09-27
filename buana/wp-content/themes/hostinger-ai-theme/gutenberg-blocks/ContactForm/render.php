<?php
/**
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

namespace Hostinger\AiTheme\GutenbergBlocks\ContactForm;

defined( 'ABSPATH' ) || exit;

$form_id = 'contact-form-' . uniqid();

$privacy_policy_text = $attributes['privacyPolicyText'] ?? '';
if ( empty( $privacy_policy_text ) ) {
    $privacy_policy_text = sprintf(
        '%s %s%s%s %s',
        __( 'I consent to use of provided personal data for the purpose of responding to the request as described in', 'hostinger-ai-theme' ),
        '<a href="' . esc_url( get_privacy_policy_url() ) . '" target="_blank">',
        __( 'Privacy Policy', 'hostinger-ai-theme' ),
        '</a>',
        __( 'which I have read. I may withdraw my consent at any time.', 'hostinger-ai-theme' )
    );
} ?>

<div <?php echo get_block_wrapper_attributes(); ?>>
    <section class="hts-section hts-page hts-contact-form">
        <div class="hts-details">
            <div class="hts-contact-details hts-contacts">
                <?php
                if ( $attributes['showTitle'] && ! empty( $attributes['title'] ) ) {
                    ?>
                    <h2 class="contact-form-title"><?php echo esc_html( $attributes['title'] ); ?></h2>
                <?php } ?>

                <?php
                if ( $attributes['showDescription'] && ! empty( $attributes['description'] ) ) {
                    ?>
                    <p class="contact-form-description"><?php echo esc_html( $attributes['description'] ); ?></p>
                <?php } ?>

                <form id="<?php echo esc_attr( $form_id ); ?>">
                    <?php
                    wp_nonce_field( 'submit_contactform', 'contactform_nonce' );
                    ?>

                    <label for="<?php echo esc_attr( $form_id ); ?>-name"><?php echo esc_html( $attributes['nameLabel'] ); ?></label>
                    <input type="text"
                            id="<?php echo esc_attr( $form_id ); ?>-name"
                            class="contact-name"
                            name="name"
                            placeholder="<?php echo esc_attr( $attributes['namePlaceholder'] ); ?>"
                            required>

                    <label for="<?php echo esc_attr( $form_id ); ?>-email"><?php echo esc_html( $attributes['emailLabel'] ); ?></label>
                    <input type="email"
                            id="<?php echo esc_attr( $form_id ); ?>-email"
                            class="contact-email"
                            name="email"
                            placeholder="<?php echo esc_attr( $attributes['emailPlaceholder'] ); ?>"
                            required>

                    <label for="<?php echo esc_attr( $form_id ); ?>-message">
                    <?php
                        echo esc_html( $attributes['messageLabel'] );
                    ?>
                    </label>
                    <textarea id="<?php echo esc_attr( $form_id ); ?>-message"
                                class="contact-message"
                                name="message"
                                placeholder="<?php echo esc_attr( $attributes['messagePlaceholder'] ); ?>"
                                required></textarea>

                    <div class="validate-message"></div>

                    <div class="hts-privacy-agree">
                        <label class="hts-form-control">
                            <input type="checkbox"
                                    id="<?php echo esc_attr( $form_id ); ?>-privacy-policy-checkbox"
                                    class="privacy-policy-checkbox"
                                    name="privacy_policy"
                                    required>
                            <span>
                            <?php
                                echo wp_kses_post( $privacy_policy_text );
                            ?>
                            </span>
                        </label>
                    </div>

                    <input type="submit"
                            class="btn wp-element-button"
                            value="<?php echo esc_attr( $attributes['buttonText'] ); ?>"/>
                </form>
            </div>
        </div>
    </section>
</div>
