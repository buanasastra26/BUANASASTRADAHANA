<?php

namespace Hostinger\Reach\Providers;

use Hostinger\Amplitude\AmplitudeManager;
use Hostinger\Reach\Amplitude\Amplitude;
use Hostinger\Reach\Container;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Utils as Helper;


if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class AmplitudeProvider implements ProviderInterface {
    public function register( Container $container ): void {
        $container->set(
            Amplitude::class,
            function () use ( $container ) {
                return new AmplitudeManager(
                    $container->get( Helper::class ),
                    $container->get( Config::class ),
                    $container->get( Client::class ),
                );
            }
        );
    }
}
