<?php

namespace Hostinger\AiAssistant\Providers;

use Hostinger\AiAssistant\Cli\JwtCommand;
use Hostinger\AiAssistant\Container;
use Hostinger\AiAssistant\Mcp\Rest\JwtAuth;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class CliProvider implements ProviderInterface {
    public function register( Container $container ): void {
        if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return;
        }

        $jwt_auth = $container->get( JwtAuth::class );

        \WP_CLI::add_command( 'hostinger-ai jwt', new JwtCommand( $jwt_auth ) );
    }
}
