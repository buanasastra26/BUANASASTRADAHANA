<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class GenerationJobStore {
    public const JOB_OPTION = 'hostinger_ai_generation_job';

    public const START_LOCK_OPTION = 'hostinger_ai_generation_start_lock';

    public const PHASE_STARTING = 'starting';

    public const PHASE_PENDING = 'pending';

    public const PHASE_APPLYING = 'applying';

    public const PHASE_APPLIED = 'applied';

    public const PHASE_FAILED = 'failed';

    public const JOB_TIMEOUT_SECONDS = 300;

    public const START_LOCK_TTL = 120;

    public const CLAIM_TAKEN = 'taken';

    public const CLAIM_HELD = 'held';

    public const CLAIM_ERROR = 'error';

    public function get(): array {
        $job = get_option( self::JOB_OPTION, array() );

        return is_array( $job ) ? $job : array();
    }

    public function save( array $job ): void {
        if ( ! array_key_exists( 'started_at', $job ) ) {
            $existing = $this->get();

            if (
                isset( $existing['started_at'] )
                && ( $existing['job_id'] ?? null ) === ( $job['job_id'] ?? null )
            ) {
                $job['started_at'] = $existing['started_at'];
            }
        }

        update_option( self::JOB_OPTION, $job, false );
    }

    public function delete(): void {
        delete_option( self::JOB_OPTION );
    }

    public function get_fresh(): array {
        wp_cache_delete( self::JOB_OPTION, 'options' );

        return $this->get();
    }

    public function request_key( array $job ): string {
        return (string) ( $job['request_key'] ?? $job['job_id'] ?? '' );
    }

    public function ensure_started_at( array $job ): array {
        if ( (int) ( $job['started_at'] ?? 0 ) > 0 ) {
            return $job;
        }

        $job['started_at'] = time();
        $this->save( $job );

        return $job;
    }

    public function timed_out( array $job ): bool {
        return ( time() - (int) ( $job['started_at'] ?? 0 ) ) >= self::JOB_TIMEOUT_SECONDS;
    }

    public function claim_start( string $request_key ): string {
        global $wpdb;

        $raw = $this->raw_start_claim();

        if ( null !== $raw && $this->raw_claim_abandoned( $raw ) ) {
            $this->delete_start_claim( $raw );
        }

        $claim = maybe_serialize(
            array(
                'request_key' => $request_key,
                'claimed_at'  => time(),
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $claimed = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'off') /* LOCK */",
                self::START_LOCK_OPTION,
                $claim
            )
        );

        if ( false === $claimed ) {
            Helper::log( 'Failed to claim the generation start: ' . mb_substr( (string) $wpdb->last_error, 0, 200 ) );

            return self::CLAIM_ERROR;
        }

        if ( ! $claimed ) {
            return self::CLAIM_HELD;
        }

        wp_cache_delete( self::START_LOCK_OPTION, 'options' );
        wp_cache_delete( 'notoptions', 'options' );

        return self::CLAIM_TAKEN;
    }

    public function start_claim(): array {
        $raw = $this->raw_start_claim();

        if ( null === $raw ) {
            return array();
        }

        $claim = maybe_unserialize( $raw );

        return is_array( $claim ) ? $claim : array();
    }

    public function start_claim_abandoned(): bool {
        $raw = $this->raw_start_claim();

        return null === $raw || $this->raw_claim_abandoned( $raw );
    }

    public function release_start( string $request_key ): void {
        $raw = $this->raw_start_claim();

        if ( null === $raw ) {
            return;
        }

        $claim = maybe_unserialize( $raw );

        if ( ! is_array( $claim ) || ( $claim['request_key'] ?? null ) !== $request_key ) {
            return;
        }

        $this->delete_start_claim( $raw );
    }

    public function force_release_start(): void {
        delete_option( self::START_LOCK_OPTION );
    }

    public function touch_start_claim( string $request_key ): void {
        $raw = $this->raw_start_claim();

        if ( null === $raw ) {
            return;
        }

        $claim = maybe_unserialize( $raw );

        if ( ! is_array( $claim ) || ( $claim['request_key'] ?? null ) !== $request_key ) {
            return;
        }

        $this->extend_start_claim( $raw, $request_key );
    }

    public function is_live( array $job ): bool {
        $phase = (string) ( $job['phase'] ?? '' );

        if ( self::PHASE_STARTING === $phase ) {
            return ! $this->start_claim_abandoned();
        }

        if ( self::PHASE_PENDING !== $phase && self::PHASE_APPLYING !== $phase ) {
            return false;
        }

        if ( (int) ( $job['started_at'] ?? 0 ) < 1 ) {
            return true;
        }

        return ! $this->timed_out( $job );
    }

    private function raw_start_claim(): ?string {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `option_value` FROM `$wpdb->options` WHERE `option_name` = %s",
                self::START_LOCK_OPTION
            )
        );

        return null === $value ? null : (string) $value;
    }

    private function raw_claim_abandoned( string $raw_value ): bool {
        $claim      = maybe_unserialize( $raw_value );
        $claimed_at = is_array( $claim ) ? (int) ( $claim['claimed_at'] ?? 0 ) : 0;

        return ( time() - $claimed_at ) > self::START_LOCK_TTL;
    }

    protected function delete_start_claim( string $expected_value ): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `$wpdb->options` WHERE `option_name` = %s AND `option_value` = %s",
                self::START_LOCK_OPTION,
                $expected_value
            )
        );

        wp_cache_delete( self::START_LOCK_OPTION, 'options' );
        wp_cache_delete( 'notoptions', 'options' );
    }

    protected function extend_start_claim( string $expected_value, string $request_key ): void {
        global $wpdb;

        $updated = maybe_serialize(
            array(
                'request_key' => $request_key,
                'claimed_at'  => time(),
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE `$wpdb->options` SET `option_value` = %s WHERE `option_name` = %s AND `option_value` = %s /* LOCK */",
                $updated,
                self::START_LOCK_OPTION,
                $expected_value
            )
        );

        wp_cache_delete( self::START_LOCK_OPTION, 'options' );
        wp_cache_delete( 'notoptions', 'options' );
    }
}
