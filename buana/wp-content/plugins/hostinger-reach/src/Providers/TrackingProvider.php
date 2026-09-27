<?php

namespace Hostinger\Reach\Providers;

use Hostinger\Reach\Api\Webhooks\Handlers\CartAbandoned;
use Hostinger\Reach\Container;
use Hostinger\Reach\Functions;
use Hostinger\Reach\Repositories\CartRepository;
use Hostinger\Reach\Tracking\AbandonedCarts;
use Hostinger\Reach\Tracking\RestoreCart;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class TrackingProvider implements ProviderInterface {
    public function register( Container $container ): void {
        $container->set(
            AbandonedCarts::class,
            function () use ( $container ) {
                return new AbandonedCarts(
                    $container->get( CartRepository::class ),
                    $container->get( CartAbandoned::class ),
                    $container->get( Functions::class )
                );
            }
        );

        $container->set(
            RestoreCart::class,
            function () use ( $container ) {
                return new RestoreCart(
                    $container->get( CartRepository::class )
                );
            }
        );

        $abandoned_carts = $container->get( AbandonedCarts::class );
        $abandoned_carts->init();

        $restore_cart = $container->get( RestoreCart::class );
        $restore_cart->init();
    }
}
