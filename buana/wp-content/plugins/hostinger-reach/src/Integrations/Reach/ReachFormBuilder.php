<?php

namespace Hostinger\Reach\Integrations\Reach;

use Hostinger\Reach\Blocks\SubscriptionFormBlock;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReachFormBuilder {

    public const SCRIPT_HANDLE  = 'hostinger-reach-embed';
    public const SHORTCODE_NAME = 'hostinger_reach_form';

    public function init(): void {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_embed_script' ) );
        add_shortcode( self::SHORTCODE_NAME, array( $this, 'render_shortcode' ) );
    }

    public function enqueue_embed_script(): void {
        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            HOSTINGER_REACH_EMBED_SCRIPT_URL,
            array(),
            null,
            array(
                'in_footer' => true,
                'strategy'  => 'defer',
            )
        );
    }

    public function render_shortcode( mixed $atts ): string {
        $atts = shortcode_atts(
            array(
                'formbuilderid' => '',
            ),
            is_array( $atts ) ? $atts : array(),
            self::SHORTCODE_NAME
        );

        $form_builder_id = sanitize_text_field( $atts['formbuilderid'] );

        if ( empty( $form_builder_id ) ) {
            return '';
        }

        ob_start();
        SubscriptionFormBlock::render_block_html( array( 'formBuilderId' => $form_builder_id ) );

        return (string) ob_get_clean();
    }
}
