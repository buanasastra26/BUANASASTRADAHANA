<?php

namespace Hostinger\Reach\Integrations\Forminator;

use Hostinger\Reach\Dto\PluginData;
use Hostinger\Reach\Dto\ReachContact;
use Hostinger\Reach\Integrations\Integration;
use Hostinger\Reach\Integrations\IntegrationInterface;
use Forminator_Form_Entry_Model;

class ForminatorIntegration extends Integration implements IntegrationInterface {

    public const INTEGRATION_NAME = 'forminator';

    public static function get_name(): string {
        return self::INTEGRATION_NAME;
    }

    public function get_post_type(): array {
        return array( 'forminator_forms' );
    }

    public function get_plugin_data(): PluginData {
        return PluginData::from_array(
            array(
                'id'             => self::INTEGRATION_NAME,
                'folder'         => 'forminator',
                'file'           => 'forminator.php',
                'admin_url'      => 'admin.php?page=forminator',
                'add_form_url'   => 'admin.php?page=forminator-cform',
                'edit_url'       => 'admin.php?page=forminator-cform-wizard&id={post_id}',
                'url'            => 'https://wordpress.org/plugins/forminator/',
                'download_url'   => 'https://downloads.wordpress.org/plugin/forminator.zip',
                'title'          => __( 'Forminator', 'hostinger-reach' ),
                'icon'           => 'https://ps.w.org/forminator/assets/icon-256x256.gif',
                'import_enabled' => true,
            )
        );
    }

    public function active_integration_hooks(): void {
        add_action( 'forminator_form_after_save_entry', array( $this, 'handle_submission' ), 1, 2 );
    }

    public function handle_submission( int $form_id, array $submission_data ): void {
        if ( ! $this->is_form_enabled( $form_id ) ) {
            return;
        }

        $name  = $this->get_form_title( $form_id );
        $email = $this->find_email( $_POST );
        if ( empty( $email ) ) {
            return;
        }

        do_action(
            'hostinger_reach_submit',
            array(
                'group'    => $name ?? self::INTEGRATION_NAME,
                'email'    => $email,
                'metadata' => array(
                    'plugin'  => self::INTEGRATION_NAME,
                    'form_id' => $form_id,
                ),
            )
        );
    }

    public function get_import_summary(): array {
        $forms   = $this->get_forms();
        $summary = array();

        foreach ( $forms as $form ) {
            $form_id = $form['form_id'] ?? null;

            if ( ! $form_id ) {
                continue;
            }

            $title = $this->get_form_title( $form_id );

            $contacts = 0;
            if ( class_exists( 'Forminator_Form_Entry_Model' ) ) {
                $contacts = Forminator_Form_Entry_Model::count_entries( $form_id );
            }

            $summary[ $form_id ] = array(
                'title'    => $title,
                'contacts' => (int) $contacts,
            );
        }

        return $summary;
    }

    public function get_contacts( mixed $form_id = null, ?int $limit = 100, ?int $offset = 0 ): array {
        if ( ! class_exists( 'Forminator_Form_Entry_Model' ) ) {
            return array();
        }

        $entries = Forminator_Form_Entry_Model::query_entries(
            array(
                'form_id'  => $form_id,
                'per_page' => $limit,
                'offset'   => $offset,
            )
        );

        $entries = array_filter(
            $entries,
            function ( Forminator_Form_Entry_Model $entry ) {
                return $this->find_email( $entry->meta_data );
            }
        );

        $entries = array_map(
            function ( Forminator_Form_Entry_Model $entry ) use ( $form_id ) {
                $contact = new ReachContact(
                    $this->find_email( $entry->meta_data ),
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

    private function get_form_title( int $form_id ): string {
        $title = '';
        if ( function_exists( 'forminator_get_form_name' ) ) {
            $title = forminator_get_form_name( $form_id );
        }

        if ( empty( $title ) ) {
            $title = $this->get_name() . ' - ' . $form_id;
        }

        return $title;
    }

    public function get_forms(): array {
        $forms = parent::get_forms();

        return array_map(
            function ( array $form ) {
                $form['form_title'] = $this->get_form_title( $form['form_id'] );
                return $form;
            },
            $forms
        );
    }

    private function find_email( array $data ): string {
        foreach ( $data as $key => $value ) {

            // Sometimes the values are coming from POST request (submissions) and others from FormModel object (import).
            // In FormModel the values are stored as arrays.
            if ( is_array( $value ) ) {
                $value = $value['value'] ?? '';
            }

            $sanitized_value = sanitize_email( $value );
            if ( filter_var( $sanitized_value, FILTER_VALIDATE_EMAIL ) ) {
                return $value;
            }
        }

        return '';
    }
}
