<?php

namespace Hostinger\AiAssistant\Providers;

use Hostinger\AiAssistant\Container;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Constants;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ContainerProvider implements ProviderInterface {
    public function register( Container $container ): void {
        $container->set( Container::class, fn() => $container );

        $container->set(
            Client::class,
            static fn( Container $c ) => new Client(
                $c->get( Config::class )->getConfigValue( 'base_rest_uri', Constants::HOSTINGER_REST_URI ),
                array(
                    Config::TOKEN_HEADER  => Utils::getApiToken(),
                    Config::DOMAIN_HEADER => $c->get( Utils::class )->getHostInfo(),
                )
            )
        );
    }
}
