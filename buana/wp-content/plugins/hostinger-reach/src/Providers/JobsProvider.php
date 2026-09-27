<?php

namespace Hostinger\Reach\Providers;

use Hostinger\Reach\Api\Handlers\ReachApiHandler;
use Hostinger\Reach\Api\Webhooks\Handlers\CartAbandoned;
use Hostinger\Reach\Container;
use Hostinger\Reach\Integrations\ImportManager;
use Hostinger\Reach\Jobs\AbandonedCartsJob;
use Hostinger\Reach\Jobs\ActionScheduler;
use Hostinger\Reach\Jobs\ImportJob;
use Hostinger\Reach\Jobs\CleanupCartsJob;
use Hostinger\Reach\Repositories\CartRepository;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class JobsProvider implements ProviderInterface {

    public function register( Container $container ): void {
        $action_scheduler = new ActionScheduler();
        $container->set(
            ActionScheduler::class,
            function () use ( $action_scheduler ) {
                return $action_scheduler;
            }
        );

        $jobs = array(
            AbandonedCartsJob::class => array(
                $container->get( ActionScheduler::class ),
                $container->get( ReachApiHandler::class ),
                $container->get( CartRepository::class ),
                $container->get( CartAbandoned::class ),
            ),
            ImportJob::class         => array(
                $container->get( ActionScheduler::class ),
                $container->get( ReachApiHandler::class ),
                $container->get( ImportManager::class ),
            ),
            CleanupCartsJob::class   => array(
                $container->get( ActionScheduler::class ),
                $container->get( ReachApiHandler::class ),
                $container->get( CartRepository::class ),
            ),
        );

        foreach ( $jobs as $class_name => $dependencies ) {
            $job = new $class_name( ...$dependencies );
            $container->set(
                $job::class,
                function () use ( $job ) {
                    return $job;
                }
            );

            $job = $container->get( $job::class );
            add_action(
                'init',
                function () use ( $job ): void {
                    $job->init();
                }
            );

        }
    }
}
