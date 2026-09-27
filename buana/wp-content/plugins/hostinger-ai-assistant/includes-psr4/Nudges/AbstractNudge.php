<?php

namespace Hostinger\AiAssistant\Nudges;

use Hostinger\AiAssistant\Nudges\Dto\ReachOut;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

abstract class AbstractNudge implements NudgeInterface {
    protected const THROTTLE_TTL = DAY_IN_SECONDS;
    protected const SHOWN_SNOOZE = 2 * DAY_IN_SECONDS;
    protected const MAX_SHOWS    = 3;
    protected const LONG_BACKOFF = 30 * DAY_IN_SECONDS;
    protected const DISMISS_TTL  = 7 * DAY_IN_SECONDS;

    protected const MIN_SITE_AGE_DAYS     = 0;
    protected const MIN_RELEVANT_AGE_DAYS = 0;

    abstract public function get_name(): string;

    abstract protected function build_reach_out(): ?ReachOut;

    final public function evaluate(): ?ReachOut {
        if ( $this->is_dismissed() ) {
            return null;
        }

        $reach_out = $this->build_reach_out();

        if ( $reach_out === null || ! $this->is_site_old_enough() || ! $this->is_relevant_long_enough() ) {
            return null;
        }

        if ( ! $this->can_show( $reach_out->get_dedup_key() ) ) {
            return null;
        }

        return $reach_out;
    }

    final public function touch_relevance(): void {
        if ( static::MIN_RELEVANT_AGE_DAYS <= 0 ) {
            return;
        }

        if ( (int) get_option( $this->relevant_since_key(), 0 ) > 0 ) {
            return;
        }

        if ( $this->build_reach_out() === null ) {
            return;
        }

        update_option( $this->relevant_since_key(), time() );
    }

    public function is_enabled(): bool {
        return true;
    }

    public function get_priority(): int {
        return 0;
    }

    public function is_throttled(): bool {
        return (bool) get_transient( $this->throttle_key() );
    }

    public function touch_throttle(): void {
        set_transient( $this->throttle_key(), 1, static::THROTTLE_TTL );
    }

    public function mark_sent( ReachOut $reach_out ): void {
        $this->record_shown( $reach_out->get_dedup_key() );
    }

    public function mark_dismissed(): void {
        update_option( $this->dismiss_key(), time() );
    }

    public function is_dismissed(): bool {
        $dismissed_at = (int) get_option( $this->dismiss_key(), 0 );

        return $dismissed_at > 0 && ( time() - $dismissed_at ) < static::DISMISS_TTL;
    }

    protected function is_site_old_enough(): bool {
        if ( static::MIN_SITE_AGE_DAYS <= 0 ) {
            return true;
        }

        return $this->get_site_age_days() >= static::MIN_SITE_AGE_DAYS;
    }

    protected function is_relevant_long_enough(): bool {
        if ( static::MIN_RELEVANT_AGE_DAYS <= 0 ) {
            return true;
        }

        $since = (int) get_option( $this->relevant_since_key(), 0 );

        return $since > 0 && ( time() - $since ) >= ( static::MIN_RELEVANT_AGE_DAYS * DAY_IN_SECONDS );
    }

    protected function get_site_age_days(): int {
        $oldest_user = get_users(
            array(
                'number'  => 1,
                'orderby' => 'registered',
                'order'   => 'ASC',
                'fields'  => array( 'user_registered' ),
            )
        );

        $registered = isset( $oldest_user[0]->user_registered ) ? strtotime( (string) $oldest_user[0]->user_registered ) : false;

        if ( ! $registered ) {
            return 0;
        }

        return (int) floor( ( time() - $registered ) / DAY_IN_SECONDS );
    }

    protected function can_show( string $dedup_key ): bool {
        $state = $this->get_shown_state();

        if ( $state['key'] !== $dedup_key ) {
            return true;
        }

        $elapsed = time() - $state['at'];

        if ( $state['count'] >= static::MAX_SHOWS ) {
            return $elapsed >= static::LONG_BACKOFF;
        }

        return $elapsed >= static::SHOWN_SNOOZE;
    }

    protected function record_shown( string $dedup_key ): void {
        $state = $this->get_shown_state();

        $new_cycle       = $state['key'] !== $dedup_key;
        $backoff_elapsed = $state['count'] >= static::MAX_SHOWS
            && ( time() - $state['at'] ) >= static::LONG_BACKOFF;

        $count = ( $new_cycle || $backoff_elapsed ) ? 0 : $state['count'];

        update_option(
            $this->state_key(),
            array(
                'key'   => $dedup_key,
                'count' => $count + 1,
                'at'    => time(),
            )
        );
    }

    protected function reset_state(): void {
        delete_option( $this->state_key() );
    }

    private function get_shown_state(): array {
        $state = get_option( $this->state_key(), array() );

        if ( ! is_array( $state ) ) {
            $state = array();
        }

        return array(
            'key'   => isset( $state['key'] ) ? (string) $state['key'] : '',
            'count' => isset( $state['count'] ) ? (int) $state['count'] : 0,
            'at'    => isset( $state['at'] ) ? (int) $state['at'] : 0,
        );
    }

    protected function throttle_key(): string {
        return 'hostinger_ai_nudge_' . $this->get_name() . '_checked';
    }

    protected function state_key(): string {
        return 'hostinger_ai_nudge_' . $this->get_name() . '_shown';
    }

    protected function dismiss_key(): string {
        return 'hostinger_ai_nudge_' . $this->get_name() . '_dismissed';
    }

    protected function relevant_since_key(): string {
        return 'hostinger_ai_nudge_' . $this->get_name() . '_relevant_since';
    }
}
