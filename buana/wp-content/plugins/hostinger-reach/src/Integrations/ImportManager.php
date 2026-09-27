<?php

namespace Hostinger\Reach\Integrations;

class ImportManager {

    public const IMPORT_STATUS_OPTION_KEY         = 'hostinger_reach_integrations_import_status';
    public const IMPORT_STATUS_PARTIALLY_IMPORTED = 'partially_imported';
    public const IMPORT_STATUS_NOT_IMPORTED       = 'not_imported';
    public const IMPORT_STATUS_IMPORTING          = 'importing';
    public const IMPORT_STATUS_IMPORTED           = 'imported';
    public const START_HOOK_JOB_NAME              = 'hostinger-reach/jobs/import/start';

    public function import( array $integrations ): void {
        $this->set_import_status( $integrations, self::IMPORT_STATUS_IMPORTING );

        foreach ( $integrations as $integration => $forms ) {
            if ( ! $this->is_import_enabled( $integration ) ) {
                continue;
            }

            foreach ( $forms as $form ) {
                do_action(
                    self::START_HOOK_JOB_NAME,
                    array(
                        'integration' => $integration,
                        'form_id'     => $form,
                    )
                );
            }
        }
    }

    public function get_status( string $integration ): array {
        $import_status = $this->get_import_status();
        $summary       = apply_filters( 'hostinger_reach_import_summary_' . $integration, array() );

        foreach ( $summary as $form_id => $data ) {
            $form_status                   = $import_status[ $integration ][ $form_id ] ?? self::IMPORT_STATUS_NOT_IMPORTED;
            $summary[ $form_id ]['status'] = $form_status;
        }

        return array(
            'status'  => $this->get_status_from_summary( $summary ),
            'total'   => $this->get_total_from_summary( $summary ),
            'summary' => $summary,
        );
    }

    public function is_import_enabled( string $integration ): bool {
        return apply_filters( 'hostinger_reach_import_enabled', false, $integration );
    }

    public function get_contacts( string $integration, mixed $form_id = null, ?int $limit = 100, ?int $offset = 0 ): array {
        return apply_filters( 'hostinger_reach_contacts_' . $integration, $form_id, $limit, $offset );
    }

    public function set_completed_import( string $integration, array $forms ): void {
        $this->set_import_status(
            array(
                $integration => $forms,
            ),
            self::IMPORT_STATUS_IMPORTED
        );
    }

    private function set_import_status( array $integrations, string $status ): void {
        $import_status = $this->get_import_status();

        foreach ( $integrations as $integration => $forms ) {
            if ( ! $this->is_import_enabled( $integration ) ) {
                continue;
            }
            foreach ( $forms as $form_id ) {
                if ( ! is_array( $import_status[ $integration ] ?? array() ) ) {
                    $import_status[ $integration ] = array();
                }
                $import_status[ $integration ][ $form_id ] = $status;
            }
        }

        update_option( self::IMPORT_STATUS_OPTION_KEY, $import_status );
    }

    private function get_import_status(): array {
        return get_option( self::IMPORT_STATUS_OPTION_KEY, array() );
    }

    private function get_total_from_summary( array $summary ): int {
        return array_sum( wp_list_pluck( $summary, 'contacts' ) );
    }

    private function get_status_from_summary( array $summary ): string {
        $has_any_importing = $this->array_find(
            $summary,
            function ( $item ) {
                return $item['status'] === self::IMPORT_STATUS_IMPORTING;
            }
        );

        if ( ! is_null( $has_any_importing ) ) {
            return self::IMPORT_STATUS_IMPORTING;
        }

        $imported = count(
            array_filter(
                $summary,
                function ( $item ) {
                    return $item['status'] === self::IMPORT_STATUS_IMPORTED;
                }
            )
        );

        if ( $imported === 0 ) {
            return self::IMPORT_STATUS_NOT_IMPORTED;
        } elseif ( $imported >= count( $summary ) ) {
            return self::IMPORT_STATUS_IMPORTED;
        } else {
            return self::IMPORT_STATUS_PARTIALLY_IMPORTED;
        }
    }

    /**
     * Polyfill for `array_find()`
     *
     * Searches an array for the first element that passes a given callback.
     *
     * @since 6.8.0
     *
     * @param array    $array    The array to search.
     * @param callable $callback The callback to run for each element.
     * @return mixed|null The first element in the array that passes the `$callback`, otherwise null.
     */
    public function array_find( array $array, callable $callback ): mixed { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
        foreach ( $array as $key => $value ) {
            if ( $callback( $value, $key ) ) {
                return $value;
            }
        }

        return null;
    }
}
