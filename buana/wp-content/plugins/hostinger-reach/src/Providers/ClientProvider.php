<?php

namespace Hostinger\Reach\Providers;

use Hostinger\Reach\Container;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Constants;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils as Helper;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ClientProvider implements ProviderInterface {

    public function register( Container $container ): void {
        $container->set( Helper::class, fn() => new Helper() );
        $container->set( Config::class, fn() => new Config() );
        $container->set(
            Client::class,
            function () use ( $container ) {
                return new Client(
                    $container->get( Config::class )->getConfigValue(
                        'base_rest_uri',
                        Constants::HOSTINGER_REST_URI
                    ),
                    array(
                        Config::TOKEN_HEADER  => apply_filters( 'hostinger_api_token', $container->get( Helper::class )->getApiToken() ),
                        Config::DOMAIN_HEADER => apply_filters( 'hostinger_domain', $container->get( Helper::class )->getHostInfo() ),
                    )
                );
            }
        );
    }
}
