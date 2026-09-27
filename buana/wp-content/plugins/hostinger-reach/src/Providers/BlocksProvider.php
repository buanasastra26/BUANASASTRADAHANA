<?php

namespace Hostinger\Reach\Providers;

use Hostinger\Reach\Blocks\SubscriptionFormBlock;
use Hostinger\Reach\Container;
use Hostinger\Reach\Setup\Blocks;
use Hostinger\Reach\Setup\Assets;
use Hostinger\Reach\Functions;
use Hostinger\Reach\Api\Handlers\ReachApiHandler;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class BlocksProvider implements ProviderInterface {
    public function register( Container $container ): void {
        $container->set(
            SubscriptionFormBlock::class,
            function () use ( $container ) {
                return new SubscriptionFormBlock(
                    $container->get( Assets::class ),
                    $container->get( Functions::class ),
                    $container->get( ReachApiHandler::class )
                );
            }
        );

        $container->set(
            Blocks::class,
            function () use ( $container ) {
                return new Blocks( $container->get( SubscriptionFormBlock::class ) );
            }
        );

        $blocks = $container->get( Blocks::class );
        $blocks->init();
    }
}
