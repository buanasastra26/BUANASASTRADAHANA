<?php

namespace Hostinger\AiAssistant\Nudges\Dto;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Value object returned by a nudge when it should fire.
 *
 * - message:   the copy Kodee shows to the user.
 * - dedup_key: a stable value identifying the trigger window, persisted after a
 *              successful send so the same nudge does not repeat within it.
 * - visuals:   optional reach-out visuals (e.g. a CTA button) the chatbot
 *              backend maps to the widget's built-in `webhookVisual` renderer.
 */
class ReachOut {
    /**
     * @param array<int, array<string, mixed>> $visuals
     */
    public function __construct(
        private string $message,
        private string $dedup_key,
        private array $visuals = array()
    ) {
    }

    public function get_message(): string {
        return $this->message;
    }

    public function get_dedup_key(): string {
        return $this->dedup_key;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_visuals(): array {
        return $this->visuals;
    }
}
