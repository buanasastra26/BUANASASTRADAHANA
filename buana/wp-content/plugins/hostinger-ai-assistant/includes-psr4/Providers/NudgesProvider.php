<?php

namespace Hostinger\AiAssistant\Providers;

use Hostinger\AiAssistant\Container;
use Hostinger\AiAssistant\Nudges\DomainNudge;
use Hostinger\AiAssistant\Nudges\DomainRenewalNudge;
use Hostinger\AiAssistant\Nudges\NudgeManager;
use Hostinger\AiAssistant\Nudges\ReachSetupNudge;
use Hostinger\AiAssistant\Nudges\RenewalNudge;
use Hostinger\AiAssistant\Nudges\ResourceLimitNudge;
use Hostinger\AiAssistant\Nudges\SearchConsoleNudge;
use Hostinger\AiAssistant\Nudges\WooCommerceSetupNudge;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Wires the proactive Kodee nudges.
 *
 * To add a new nudge (e.g. domain renewal): create a class implementing
 * NudgeInterface (usually extending AbstractNudge) and register it below.
 */
class NudgesProvider implements ProviderInterface {
    public function register( Container $container ): void {
        $manager = $container->get( NudgeManager::class );

        $manager->register( $container->get( RenewalNudge::class ) );
        $manager->register( $container->get( DomainRenewalNudge::class ) );
        $manager->register( $container->get( ReachSetupNudge::class ) );
        $manager->register( $container->get( WooCommerceSetupNudge::class ) );
        $manager->register( $container->get( DomainNudge::class ) );
        $manager->register( $container->get( ResourceLimitNudge::class ) );
        $manager->register( $container->get( SearchConsoleNudge::class ) );

        $manager->init();
    }
}
