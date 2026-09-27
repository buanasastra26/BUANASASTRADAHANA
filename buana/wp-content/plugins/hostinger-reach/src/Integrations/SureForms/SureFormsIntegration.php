<?php

namespace Hostinger\Reach\Integrations\SureForms;

use Hostinger\Reach\Dto\PluginData;
use Hostinger\Reach\Dto\ReachContact;
use Hostinger\Reach\Integrations\Integration;
use Hostinger\Reach\Integrations\IntegrationInterface;
use SRFM\Inc\Database\Tables\Entries;
use SRFM\Inc\Helper;

class SureFormsIntegration extends Integration implements IntegrationInterface {

    public const INTEGRATION_NAME = 'sureforms';

    public static function get_name(): string {
        return self::INTEGRATION_NAME;
    }

    public function get_post_type(): array {
        return array( 'sureforms_form' );
    }

    public function get_plugin_data(): PluginData {
        return PluginData::from_array(
            array(
                'id'             => self::INTEGRATION_NAME,
                'folder'         => 'sureforms',
                'file'           => 'sureforms.php',
                'admin_url'      => 'admin.php?page=sureforms_menu',
                'add_form_url'   => 'admin.php?page=add-new-form',
                'edit_url'       => 'post.php?post={post_id}&action=edit',
                'url'            => 'https://wordpress.org/plugins/sureforms/',
                'download_url'   => 'https://downloads.wordpress.org/plugin/sureforms.zip',
                'title'          => __( 'Sure Forms', 'hostinger-reach' ),
                'icon'           => 'https://ps.w.org/sureforms/assets/icon-256x256.gif',
                'import_enabled' => true,
            )
        );
    }

    public function init(): void {
        parent::init();
        add_action( 'hostinger_reach_integration_activated', array( $this, 'set_onboarding_skipped' ) );
    }

    public function set_onboarding_skipped( string $name ): void {
        if ( $name !== self::INTEGRATION_NAME || ! class_exists( 'SRFM\Inc\Helper' ) ) {
            return;
        }
        update_option( '__srfm_do_redirect', false );
        Helper::update_srfm_option( 'onboarding_completed', 'yes' );
    }

    public function active_integration_hooks(): void {
        add_action( 'srfm_form_submit', array( $this, 'handle_submission' ) );
    }

    public function get_import_summary(): array {
        $forms   = $this->get_forms();
        $summary = array();

        if ( ! class_exists( 'SRFM\Inc\Database\Tables\Entries' ) ) {
            return $summary;
        }

        foreach ( $forms as $form ) {
            $form_id = $form['form_id'] ?? null;

            if ( ! $form_id ) {
                continue;
            }

            $contacts            = Entries::get_total_entries_by_status( 'all', $form_id );
            $summary[ $form_id ] = array(
                'title'    => $form['post']['post_title'] ?? self::INTEGRATION_NAME,
                'contacts' => (int) $contacts,
            );
        }

        return $summary;
    }

    public function get_contacts( mixed $form_id = null, ?int $limit = 100, ?int $offset = 0 ): array {
        if ( ! class_exists( 'SRFM\Inc\Database\Tables\Entries' ) ) {
            return array();
        }

        $args = array(
            'where'  => array(
                array(
                    array(
                        'key'     => 'form_id',
                        'value'   => $form_id,
                        'compare' => '=',
                    ),
                ),
            ),
            'limit'  => $limit,
            'offset' => $offset,
        );

        $entries = Entries::get_all( $args );
        $entries = array_map(
            function ( array $entry ) use ( $form_id ) {
                $contact = new ReachContact(
                    $this->find_email( $entry['form_data'] ?? array() ),
                    '',
                    '',
                    array(
                        'plugin'  => self::INTEGRATION_NAME,
                        'form_id' => $form_id,
                        'group'   => $this->get_form_title( $form_id ),
                    )
                );

                return $contact->to_array();
            },
            $entries
        );

        return $entries;
    }

    public function handle_submission( array $submission_data ): void {
        if ( ! $this->is_form_enabled( $submission_data['form_id'] ) ) {
            return;
        }

        $data  = $submission_data['data'] ?? array();
        $email = $data['email'] ?? null;

        if ( $email ) {
            do_action(
                'hostinger_reach_submit',
                array(
                    'group'    => $submission_data['form_name'] ?? self::INTEGRATION_NAME,
                    'email'    => $email,
                    'metadata' => array(
                        'plugin'  => self::INTEGRATION_NAME,
                        'form_id' => $submission_data['form_id'],
                    ),
                )
            );
        }
    }

    private function find_email( array $data ): string {
        foreach ( $data as $value ) {
            $sanitized_value = sanitize_email( $value );
            if ( filter_var( $sanitized_value, FILTER_VALIDATE_EMAIL ) ) {
                return $value;
            }
        }

        return '';
    }

    private function get_form_title( string $form_id ): string {
        $post          = get_post( $form_id );
        $default_title = $form_id . ' ' . self::INTEGRATION_NAME;
        return $post && $post->post_title ? $post->post_title : $default_title;
    }
}
