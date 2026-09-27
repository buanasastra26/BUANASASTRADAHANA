<?php

namespace Hostinger\Reach\Integrations\Brave;

use Hostinger\Reach\Dto\PluginData;
use Hostinger\Reach\Integrations\Integration;
use Hostinger\Reach\Integrations\IntegrationInterface;

if ( ! DEFINED( 'ABSPATH' ) ) {
    exit;
}

class BraveIntegration extends Integration implements IntegrationInterface {

    public const INTEGRATION_NAME = 'brave-popup-builder';

    public function get_post_type(): array {
        return array( 'popup' );
    }

    public static function get_name(): string {
        return self::INTEGRATION_NAME;
    }

    public function get_plugin_data(): PluginData {
        return PluginData::from_array(
            array(
                'id'                  => self::INTEGRATION_NAME,
                'title'               => __( 'Brave Popup Builder', 'hostinger-reach' ),
                'folder'              => 'brave-popup-builder',
                'file'                => 'index.php',
                'admin_url'           => 'admin.php?page=bravepop-submissions',
                'add_form_url'        => 'admin.php?page=bravepop',
                'edit_url'            => 'admin.php?page=bravepop&id={post_id}',
                'url'                 => 'https://wordpress.org/plugins/brave-popup-builder/',
                'download_url'        => 'https://downloads.wordpress.org/plugin/brave-popup-builder.zip',
                'icon'                => 'https://ps.w.org/brave-popup-builder/assets/icon-256x256.gif',
                'is_view_form_hidden' => true,
                'can_toggle_forms'    => true,
            )
        );
    }

    public function active_integration_hooks(): void {
        add_action( 'bravepop_user_submitted_form', array( $this, 'handle_form_submission' ), 10, 2 );
    }

    public function handle_form_submission( string $popup_id, array $form_settings ): void {
        if ( ! $this->is_form_enabled( $popup_id ) ) {
            return;
        }

        $post  = get_post( $popup_id );
        $email = $this->find_email( $form_settings );
        if ( $email ) {
            do_action(
                'hostinger_reach_submit',
                array(
                    'group'    => $post ? $post->post_title : self::INTEGRATION_NAME,
                    'email'    => $email,
                    'metadata' => array(
                        'plugin'  => self::INTEGRATION_NAME,
                        'form_id' => $popup_id,
                    ),
                )
            );
        }
    }

    private function find_email( array $form_settings ): string {
        if ( ! isset( $form_settings['fields'] ) ) {
            return '';
        }

        foreach ( $form_settings['fields'] as $field ) {
            $value           = $field?->value ?? '';
            $sanitized_value = sanitize_email( $value );
            if ( filter_var( $sanitized_value, FILTER_VALIDATE_EMAIL ) ) {
                return $value;
            }
        }

        return '';
    }
}
