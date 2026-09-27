<?php

namespace Hostinger\Reach\Integrations\NinjaForms;

use Hostinger\Reach\Dto\ReachContact;
use Hostinger\Reach\Integrations\Integration;
use Hostinger\Reach\Integrations\IntegrationInterface;
use Hostinger\Reach\Dto\PluginData;
use Hostinger\Reach\Models\Form;
use WPN_Helper;

if ( ! DEFINED( 'ABSPATH' ) ) {
    exit;
}

class NinjaFormsIntegration extends Integration implements IntegrationInterface {

    public const INTEGRATION_NAME = 'ninja-forms';

    public function init(): void {
        parent::init();
        add_filter(
            'ninja_forms_admin_notices',
            function ( array $notices ) {
                if ( isset( $notices['one_week_support'] ) ) {
                    unset( $notices['one_week_support'] );
                }

                return $notices;
            }
        );
    }

    public function get_plugin_data(): PluginData {
        return PluginData::from_array(
            array(
                'id'             => self::INTEGRATION_NAME,
                'folder'         => 'ninja-forms',
                'file'           => 'ninja-forms.php',
                'admin_url'      => 'admin.php?page=ninja-forms',
                'add_form_url'   => 'admin.php?page=ninja-forms#new-form',
                'edit_url'       => 'admin.php?page=ninja-forms&form_id={form_id}',
                'url'            => 'https://wordpress.org/plugins/ninja-forms/',
                'download_url'   => 'https://downloads.wordpress.org/plugin/ninja-forms.zip',
                'title'          => __( 'Ninja Forms', 'hostinger-reach' ),
                'icon'           => 'https://ps.w.org/ninja-forms/assets/icon-256x256.png',
                'import_enabled' => true,
            )
        );
    }

    public static function get_name(): string {
        return self::INTEGRATION_NAME;
    }

    public function active_integration_hooks(): void {
        add_action( 'ninja_forms_after_submission', array( $this, 'handle_submission' ) );
    }

    public function handle_submission( array $data ): void {
        $form  = $this->get_form( $data['form_id'] ?? 0 );
        $email = null;

        if ( isset( $data['fields_by_key'] ) ) {
            $email = $data['fields_by_key']['email'] ?? null;
        }

        if ( isset( $email['value'] ) ) {
            $email = $email['value'];
        }

        if ( $email && $form ) {
            do_action(
                'hostinger_reach_submit',
                array(
                    'group'    => $form->get_setting( 'title' ),
                    'email'    => $email,
                    'metadata' => array(
                        'plugin'  => self::INTEGRATION_NAME,
                        'form_id' => $form->get_id(),
                    ),
                )
            );
        }
    }

    public function get_forms(): array {
        if ( ! function_exists( 'Ninja_Forms' ) ) {
            return array();
        }

        $forms_data = Ninja_Forms()->form()->get_forms();

        $forms = array_map(
            function ( $form ) {
                $form_obj = new Form(
                    array(
                        'form_id'     => $form->get_id(),
                        'form_title'  => $form->get_setting( 'title' ),
                        'is_active'   => $this->is_form_enabled( $form->get_id() ),
                        'submissions' => $this->get_submissions( $form ),
                        'type'        => $this->get_name(),
                        'post_id'     => null,
                    )
                );

                return $form_obj->to_array();
            },
            $forms_data
        );

        return $forms;
    }

    public function is_form_enabled( int $form_id ): bool {
        $form = $this->get_form( $form_id );
        if ( ! $form ) {
            return false;
        }

        $is_active_meta = $form->get_setting( self::HOSTINGER_REACH_IS_ACTIVE_META_KEY );

        if ( ! $is_active_meta ) {
            return true;
        }

        return $is_active_meta === 'yes';
    }

    public function on_form_activation_change( bool $is_updated, string $form_id, bool $is_active, string $type ): bool {
        if ( ! class_exists( 'WPN_Helper' ) ) {
            return $is_updated;
        }
        $form = $this->get_form( $form_id );
        if ( ! $form ) {
            return $is_updated;
        }

        $form->update_setting( self::HOSTINGER_REACH_IS_ACTIVE_META_KEY, $is_active ? 'yes' : 'no' );
        $form->save();
        WPN_Helper::delete_nf_cache( $form_id );

        return true;
    }

    public function update_form_submissions( array $data ): void {
        if ( ! isset( $data['metadata']['form_id'] ) ) {
            return;
        }

        $form_id = $data['metadata']['form_id'];
        $form    = $this->get_form( $form_id );

        if ( ! $form ) {
            return;
        }

        $submissions = $this->get_submissions( $form );
        $form->update_setting( self::HOSTINGER_REACH_SUBMISSIONS_META_KEY, $submissions + 1 );
        $form->save();
        WPN_Helper::delete_nf_cache( $form_id );
    }

    private function get_submissions( object $form ): int {
        $submissions = $form->get_setting( self::HOSTINGER_REACH_SUBMISSIONS_META_KEY );

        return (int) $submissions;
    }


    private function get_form( string $form_id ): ?object {
        if ( ! function_exists( 'Ninja_Forms' ) ) {
            return null;
        }

        $form_data = Ninja_Forms()->form( $form_id );
        if ( ! $form_data ) {
            return null;
        }

        return $form_data->get();
    }

    public function get_import_summary(): array {
        $summary = array();

        if ( ! function_exists( 'Ninja_Forms' ) ) {
            return $summary;
        }

        $forms = $this->get_forms();

        foreach ( $forms as $form ) {
            $form_id = $form['form_id'] ?? null;

            if ( ! $form_id ) {
                continue;
            }

            $summary[ $form_id ] = array(
                'title'    => $form['form_title'] ?? $form['form_id'],
                'contacts' => count( $this->get_form_emails( $form_id ) ),
            );
        }

        return $summary;
    }


    public function get_contacts( mixed $form_id = null, ?int $limit = 100, ?int $offset = 0 ): array {
        $entries = $this->get_form_emails( $form_id, $limit, $offset );
        $form    = $this->get_form( $form_id );

        $entries = array_map(
            function ( string $email ) use ( $form, $form_id ) {
                $title = $form->get_setting( 'title' );
                if ( empty( $title ) ) {
                    $title = $form_id . ' ' . self::INTEGRATION_NAME;
                }
                $contact = new ReachContact(
                    $email,
                    '',
                    '',
                    array(
                        'plugin' => self::INTEGRATION_NAME,
                        'group'  => $title,
                    )
                );

                return $contact->to_array();
            },
            $entries
        );

        return $entries;
    }

    private function get_form_emails( mixed $form_id = null, ?int $limit = 0, ?int $offset = 0 ): array {
        $emails = array();

        if ( ! function_exists( 'Ninja_Forms' ) || ! $form_id ) {
            return $emails;
        }

        $submissions = Ninja_Forms()->form( $form_id )->get_subs();
        $i           = 0;
        foreach ( $submissions as $submission ) {
            if ( $limit && count( $emails ) >= $limit ) {
                break;
            }

            $fields = $submission->get_field_values();

            foreach ( $fields as $value ) {
                if ( filter_var( $value, FILTER_VALIDATE_EMAIL ) && ! in_array( $value, $emails, true ) ) {
                    ++$i;
                    if ( ! $offset || $i - 1 >= $offset ) {
                        $emails[] = $value;
                    }
                }
            }
        }

        return array_unique( $emails );
    }
}
