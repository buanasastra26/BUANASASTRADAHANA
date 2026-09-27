<?php

namespace Hostinger\AiAssistant\Nudges;

use Hostinger\AiAssistant\Nudges\Dto\ReachOut;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Contract for a proactive Kodee nudge.
 *
 * Add a new nudge by implementing this (usually via {@see AbstractNudge}) and
 * registering it in {@see \Hostinger\AiAssistant\Providers\NudgesProvider}.
 */
interface NudgeInterface {
    /**
     * Unique, slug-safe identifier. Also sent as the `name` (campaign) to the
     * chatbot reach-out webhook and used to namespace the nudge's stored state.
     */
    public function get_name(): string;

    /**
     * Feature gate. Return false to keep the nudge registered but inactive.
     */
    public function is_enabled(): bool;

    /**
     * Selection weight. Higher priority nudges are evaluated first.
     */
    public function get_priority(): int;

    /**
     * Whether the nudge was already evaluated within its throttle window, so we
     * avoid hitting external APIs on every admin page load.
     */
    public function is_throttled(): bool;

    /**
     * Mark the nudge as evaluated for the current throttle window.
     */
    public function touch_throttle(): void;

    public function touch_relevance(): void;

    /**
     * Evaluate the trigger conditions, including dismissal and snooze/backoff state.
     *
     * @return ReachOut|null A reach-out to send, or null when the nudge should not fire.
     */
    public function evaluate(): ?ReachOut;

    /**
     * Persist state after a successful send so the nudge does not repeat within
     * the same trigger window.
     */
    public function mark_sent( ReachOut $reach_out ): void;

    public function mark_dismissed(): void;
}
