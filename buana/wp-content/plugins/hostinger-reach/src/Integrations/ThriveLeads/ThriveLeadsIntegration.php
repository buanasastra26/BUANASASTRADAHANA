<?php

namespace Hostinger\Reach\Integrations\ThriveLeads;

use Hostinger\Reach\Dto\PluginData;
use Hostinger\Reach\Dto\ReachContact;
use Hostinger\Reach\Integrations\Integration;
use Hostinger\Reach\Integrations\IntegrationInterface;
use WP_Post;

if ( ! DEFINED( 'ABSPATH' ) ) {
    exit;
}

class ThriveLeadsIntegration extends Integration implements IntegrationInterface {

    public const INTEGRATION_NAME = 'thrive-leads';

    public function active_integration_hooks(): void {
        if ( defined( 'TVE_LEADS_ACTION_FORM_CONVERSION' ) ) {
            add_action( TVE_LEADS_ACTION_FORM_CONVERSION, array( $this, 'handle_submission' ), 10, 6 );
        }
    }

    public function handle_submission( WP_Post $group, WP_Post $form_type, array $variation, int $test_model_id, array $post_data, mixed $current_screen ): void {
        if ( ! $this->is_form_enabled( $form_type->ID ) ) {
            return;
        }

        $contact_list = $form_type->post_title . ' - ' . $group->post_title;
        $email        = $this->find_email( $post_data );
        if ( ! empty( $email ) ) {
            do_action(
                'hostinger_reach_submit',
                array(
                    'group'    => $contact_list,
                    'email'    => $email,
                    'metadata' => array(
                        'plugin'  => self::INTEGRATION_NAME,
                        'form_id' => $form_type->ID,
                    ),
                )
            );
        }
    }

    public function get_forms(): array {
        $forms = parent::get_forms();

        return array_map(
            function ( array $form ) {
                $form['form_title'] = $this->get_form_title( $form );

                return $form;
            },
            $forms
        );
    }

    public function get_post_type(): array {
        return array( 'tve_form_type', 'tve_lead_shortcode', 'tve_lead_2s_lightbox' );
    }

    public static function get_name(): string {
        return self::INTEGRATION_NAME;
    }

    public function get_plugin_data(): PluginData {
        return PluginData::from_array(
            array(
                'id'             => self::INTEGRATION_NAME,
                'title'          => __( 'Thrive Leads', 'hostinger-reach' ),
                'folder'         => 'thrive-leads',
                'file'           => 'thrive-leads.php',
                'admin_url'      => 'admin.php?page=thrive_leads_dashboard#dashboard',
                'add_form_url'   => 'admin.php?page=thrive_leads_dashboard#dashboard',
                'edit_url'       => 'post.php?post={post_id}&action=architect&tve=true&_key=1',
                'url'            => 'https://thrivethemes.com/leads',
                'download_url'   => null,
                'import_enabled' => true,
                'icon'           => content_url( 'plugins/hostinger-reach/frontend/vue/assets/images/icons/thrive-leads-logo.png' ),
            )
        );
    }

    public function get_import_summary(): array {
        $summary['thriveLeads'] = array(
            'title'    => __( 'Thrive Leads', 'hostinger-reach' ),
            'contacts' => $this->get_contacts_count(),
        );

        return $summary;
    }

    public function get_contacts( mixed $form_id = null, ?int $limit = 100, ?int $offset = 0 ): array {
        global $wpdb;
        $table_name = $this->get_contacts_table_name();

        if ( ! $this->contacts_table_exists() ) {
            return array();
        }

        $entries = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT email, name FROM %i LIMIT %d OFFSET %d',
                $table_name,
                $limit,
                $offset
            ),
            ARRAY_A
        );
        $entries = array_map(
            function ( array $entry ) {
                $contact = new ReachContact(
                    $entry['email'] ?? '',
                    $entry['name'] ?? '',
                    '',
                    array(
                        'plugin' => self::INTEGRATION_NAME,
                        'group'  => self::INTEGRATION_NAME,
                    )
                );

                return $contact->to_array();
            },
            $entries
        );

        return $entries;
    }


    private function get_contacts_table_name(): string {
        global $wpdb;

        if ( defined( 'TVE_LEADS_DB_PREFIX' ) ) {
            $table_name = $wpdb->prefix . TVE_LEADS_DB_PREFIX . 'contacts';
        } else {
            $table_name = $wpdb->prefix . 'tve_leads_contacts';
        }

        return $table_name;
    }

    private function get_contacts_count(): int {
        global $wpdb;
        $table_name = $this->get_contacts_table_name();

        if ( ! $this->contacts_table_exists() ) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i',
                $table_name
            )
        );
    }

    private function contacts_table_exists(): bool {
        global $wpdb;

        $table_name = $this->get_contacts_table_name();

        $found = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table_name
            )
        );

        return $found === $table_name;
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

    private function get_form_title( array $form ): string {
        $title  = $form['post']['post_title'] ?? self::INTEGRATION_NAME;
        $parent = get_post_parent( $form['post']['ID'] ?? null );
        if ( ! empty( $parent ) ) {
            $title .= ' - ' . get_the_title( $parent );
        }

        return $title;
    }
}
