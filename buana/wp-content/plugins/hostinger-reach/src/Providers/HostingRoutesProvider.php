<?php

namespace Hostinger\Reach\Providers;

use Hostinger\Reach\Api\Handlers\HostingApiHandler;
use Hostinger\Reach\Api\Routes\HostingRoutes;
use Hostinger\Reach\Container;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class HostingRoutesProvider implements ProviderInterface {
    public function register( Container $container ): void {
        $routes = array(
            HostingRoutes::class => array(
                $container->get( HostingApiHandler::class ),
            ),
        );

        foreach ( $routes as $class_name => $dependencies ) {
            $router = new $class_name( ...$dependencies );
            $container->set(
                $router::class,
                function () use ( $router ) {
                    return $router;
                }
            );

            $route = $container->get( $router::class );
            $route->init();
        }
    }
}
