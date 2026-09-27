<?php

namespace Hostinger\AiAssistant;

class Functions {
    public static function log_event( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
            error_log( 'Hostinger AI Assistant Log: ' . $message );
        }
    }
}
