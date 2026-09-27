<?php

namespace Hostinger\Reach\Api\Routes;

use Hostinger\Reach\Api\ApiKeyManager;
use Hostinger\Reach\Api\Handlers\ApiHandler;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class Routes {
    private ApiHandler $handler;
    private ApiKeyManager $api_key_manager;

    public function __construct( ApiHandler $handler, ApiKeyManager $api_key_manager ) {
        $this->handler         = $handler;
        $this->api_key_manager = $api_key_manager;
    }

    public function init(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function permission_check(): bool {
        $this->disable_cache();

        return current_user_can( 'manage_options' );
    }

    public function public_permission_check(): bool {
        $this->disable_cache();

        return true;
    }

    protected function disable_cache(): void {
        if ( has_action( 'litespeed_control_set_nocache' ) ) {
            do_action(
                'litespeed_control_set_nocache',
                'Custom Rest API endpoint, not cacheable.'
            );
        }

        nocache_headers();
    }
}
