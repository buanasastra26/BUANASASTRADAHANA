<?php

namespace Hostinger\Reach\Blocks;


use Hostinger\Reach\Api\Handlers\ReachApiHandler;
use Hostinger\Reach\Api\ResourceIdManager;
use Hostinger\Reach\Functions;
use Hostinger\Reach\Integrations\Reach\ReachFormIntegration;
use Hostinger\Reach\Setup\Assets;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class SubscriptionFormBlock extends Block {
    public const ADD_BLOCK_QUERY_ARG = 'hostinger_reach_add_block';
    public const ADD_BLOCK_NONCE     = 'hostinger_reach_add_block';

    public string $name = 'subscription';
    private ReachApiHandler $reach_api_handler;

    public function __construct( Assets $assets, Functions $functions, ReachApiHandler $reach_api_handler ) {
        parent::__construct( $assets, $functions );
        $this->reach_api_handler = $reach_api_handler;
    }

    protected function get_block_editor_data(): array {
        $resource_id = $this->reach_api_handler->get_resource_id();

        if ( $resource_id === ResourceIdManager::NON_EXISTENT_RESOURCE_ID ) {
            $resource_id = '';
        }

        return array_merge(
            parent::get_block_editor_data(),
            array(
                'resource_id'  => $resource_id,
                'reach_domain' => $this->reach_api_handler->get_reach_domain(),
                'domain'       => $this->functions->get_host_info(),
            )
        );
    }

    public function data(): array {
        return array(
            'endpoint'     => esc_url_raw( rest_url() ) . 'hostinger-reach/v1/contact',
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'translations' => array(
                'thanks' => __( 'Thanks for subscribing.', 'hostinger-reach' ),
                'error'  => __( 'Something went wrong. Please try again.', 'hostinger-reach' ),
            ),
        );
    }

    public function autoloader(): void {
        if ( ! is_admin() || ! isset( $_GET[ self::ADD_BLOCK_QUERY_ARG ] ) ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::ADD_BLOCK_NONCE ) ) {
            return;
        }

        if ( $this->functions->block_file_exists( "$this->name-autoloader.js" ) === false ) {
            return;
        }

        $form_builder_id = sanitize_text_field( wp_unslash( $_GET[ self::ADD_BLOCK_QUERY_ARG ] ) );

        if ( $form_builder_id === '1' ) {
            $form_builder_id = '';
        }

        $handler = parent::get_block_name() . '-autoloader';

        wp_enqueue_script(
            $handler,
            $this->functions->get_blocks_url() . "$this->name-autoloader.js",
            array( parent::get_block_name() . '-editor' ),
            filemtime( $this->functions->get_block_file_name( "$this->name-autoloader.js" ) ),
            array( 'in_footer' => true )
        );

        wp_localize_script(
            $handler,
            'hostinger_reach_autoloader_data',
            array(
                'formBuilderId' => $form_builder_id,
            )
        );
    }

    public function render( array $attributes ): bool|string {
        ob_start();
        $is_connected = $this->reach_api_handler->is_connected();
        $this->render_block_html( $attributes, null, $is_connected );

        return ob_get_clean();
    }

    public static function render_block_html( array $attributes, ?string $plugin = null, bool $is_connected = true ): void {
        $form_builder_id = $attributes['formBuilderId'] ?? '';
        if ( ! empty( $form_builder_id ) ) {
            printf( '<div data-reach-form="%s"></div>', esc_attr( $form_builder_id ) );

            return;
        }

        $form_id      = $attributes['formId'] ?? '';
        $show_name    = $attributes['showName'] ?? false;
        $show_surname = $attributes['showSurname'] ?? false;
        $contact_list = $attributes['contactList'] ?? '';
        $tags         = $attributes['tags'] ?? array();
        $layout       = $attributes['layout'] ?? 'default';
        $is_inline    = $layout === 'inline';
        $plugin       = $plugin ?? ReachFormIntegration::INTEGRATION_NAME;
        ?>
        <div class="hostinger-reach-block-subscription-form-wrapper">

            <form id="<?php echo esc_attr( $form_id ); ?>" class="hostinger-reach-block-subscription-form">
                <input type="hidden" name="group" value="<?php echo esc_attr( $contact_list ); ?>">
                <input type="hidden" name="id" value="<?php echo esc_attr( $form_id ); ?>">
                <input type="hidden" name="tags" value="<?php echo esc_attr( implode( ',', $tags ) ); ?>">
                <input type="hidden" name="metadata.plugin" value="<?php echo esc_attr( $plugin ); ?>">

                <div
                    class="hostinger-reach-block-form-fields <?php echo esc_attr( $is_inline ? 'hostinger-reach-block-form-fields--inline' : '' ); ?>">
                    <div class="hostinger-reach-block-form-field">
                        <label
                            for="<?php echo esc_attr( $form_id ); ?>-email"><?php esc_html_e( 'Email', 'hostinger-reach' ); ?>
                            <span class="required">*</span></label>
                        <input type="email" id="<?php echo esc_attr( $form_id ); ?>-email" name="email" required>
                    </div>

                    <?php if ( $show_name ) : ?>
                        <div class="hostinger-reach-block-form-field">
                            <label
                                for="<?php echo esc_attr( $form_id ); ?>-name"><?php esc_html_e( 'Name', 'hostinger-reach' ); ?></label>
                            <input type="text" id="<?php echo esc_attr( $form_id ); ?>-name" name="name">
                        </div>
                    <?php endif; ?>

                    <?php if ( $show_surname ) : ?>
                        <div class="hostinger-reach-block-form-field">
                            <label
                                for="<?php echo esc_attr( $form_id ); ?>-surname"><?php esc_html_e( 'Surname', 'hostinger-reach' ); ?></label>
                            <input type="text" id="<?php echo esc_attr( $form_id ); ?>-surname" name="surname">
                        </div>
                    <?php endif; ?>

                    <button
                        type="submit"
                        class="hostinger-reach-block-submit has-light-color has-color-3-background-color has-text-color has-background has-link-color">
                        <?php esc_html_e( 'Subscribe', 'hostinger-reach' ); ?>
                    </button>
                </div>

                <div class="reach-subscription-message" style="display: none;">
                    <div class="reach-subscription-message__icon-success" style="display: none;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M21.0455 5.95463C21.4848 6.39397 21.4848 7.10628 21.0455 7.54562L11.5076 17.0835C10.9511 17.64 10.0489 17.64 9.49237 17.0835L5.2045 12.7956C4.76517 12.3563 4.76517 11.644 5.2045 11.2046C5.64384 10.7653 6.35616 10.7653 6.7955 11.2046L10.5 14.9091L19.4545 5.95463C19.8938 5.51529 20.6062 5.51529 21.0455 5.95463Z"
                                fill="#18181A"/>
                        </svg>
                    </div>
                    <div class="reach-subscription-message__icon-error" style="display: none;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#a10223" fill-rule="evenodd"
                                    d="M11.279 4.721a.75.75 0 0 1 0 1.06L5.782 11.28a.75.75 0 0 1-1.06-1.06l5.496-5.498a.75.75 0 0 1 1.06 0"
                                    clip-rule="evenodd"></path>
                            <path fill="#a10223" fill-rule="evenodd"
                                    d="M4.721 4.721a.75.75 0 0 0 0 1.06l5.497 5.498a.75.75 0 0 0 1.06-1.06L5.783 4.72a.75.75 0 0 0-1.06 0"
                                    clip-rule="evenodd"></path>
                            <path fill="#a10223" fill-rule="evenodd"
                                    d="M8 1.75a6.25 6.25 0 1 0 0 12.5 6.25 6.25 0 0 0 0-12.5M.25 8a7.75 7.75 0 1 1 15.5 0A7.75 7.75 0 0 1 .25 8"
                                    clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="reach-subscription-message__text">
                        <?php
                        if ( ! $is_connected && current_user_can( 'manage_options' ) ) {
                            esc_html_e( 'Your site is not connected to Reach. Connect to Reach and try again.', 'hostinger-reach' );
                        } else {
                            esc_html_e( 'Subscription form is not available at the moment', 'hostinger-reach' );
                        }
                        ?>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
}
