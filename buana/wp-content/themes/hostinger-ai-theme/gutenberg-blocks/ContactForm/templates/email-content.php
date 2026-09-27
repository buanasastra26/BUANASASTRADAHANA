<?php
/**
 * Email content template for contact form submissions
 *
 * @var string $name         The sender's name
 * @var string $email        The sender's email
 * @var string $date         The sender's date
 * @var string $form_message The sender's message
 */

$name         = $args['name'] ?? '';
$email        = $args['email'] ?? '';
$date         = $args['date'] ?? '';
$form_message = $args['form_message'] ?? '';
$site_name    = get_bloginfo( 'name' ) ?? '';
?>
New Contact Form Submission

You have received a new message through your website's contact form.

Contact Details:
Name: <?php echo esc_html( $name ); ?>

Email: <?php echo esc_html( $email ); ?>

<?php if ( ! empty( $date ) ) : ?>
Date: <?php echo esc_html( $date ); ?>
<?php endif; ?>

Message: <?php echo esc_html( $form_message ); ?>

---
This email was sent from <?php echo esc_html( $site_name ); ?>