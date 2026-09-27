<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class RequestDetacher {
    public function can_detach(): bool {
        return function_exists( 'litespeed_finish_request' ) || function_exists( 'fastcgi_finish_request' );
    }

    public function run_after_response( callable $callback ): void {
        add_action(
            'shutdown',
            function () use ( $callback ): void {
                $this->detach();
                $callback();
            }
        );
    }

    private function detach(): void {
        wp_ob_end_flush_all();

        if ( function_exists( 'litespeed_finish_request' ) ) {
            litespeed_finish_request();

            return;
        }

        if ( function_exists( 'fastcgi_finish_request' ) ) {
            fastcgi_finish_request();
        }
    }
}
