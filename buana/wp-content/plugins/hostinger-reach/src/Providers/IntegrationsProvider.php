<?php

namespace Hostinger\Reach\Providers;

use Hostinger\Reach\Api\Handlers\IntegrationsApiHandler;
use Hostinger\Reach\Api\Handlers\ReachApiHandler;
use Hostinger\Reach\Container;
use Hostinger\Reach\Functions;
use Hostinger\Reach\Integrations\Brave\BraveIntegration;
use Hostinger\Reach\Integrations\ContactForm7\ContactForm7Integration;
use Hostinger\Reach\Integrations\Elementor\ElementorIntegration;
use Hostinger\Reach\Integrations\Forminator\ForminatorIntegration;
use Hostinger\Reach\Integrations\NinjaForms\NinjaFormsIntegration;
use Hostinger\Reach\Integrations\Reach\ReachFormBuilder;
use Hostinger\Reach\Integrations\Reach\ReachFormIntegration;
use Hostinger\Reach\Integrations\SureForms\SureFormsIntegration;
use Hostinger\Reach\Integrations\ThriveLeads\ThriveLeadsIntegration;
use Hostinger\Reach\Integrations\OptInMonster\OptInMonsterIntegration;
use Hostinger\Reach\Integrations\WooCommerce\WooCommerceIntegration;
use Hostinger\Reach\Integrations\WPFormsLite\WpFormsLiteIntegration;
use Hostinger\Reach\Integrations\WSForms\WSFormsIntegration;
use Hostinger\Reach\Repositories\ContactListRepository;
use Hostinger\Reach\Repositories\FormRepository;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class IntegrationsProvider implements ProviderInterface {

    public const INTEGRATIONS = array(
        ReachFormIntegration::INTEGRATION_NAME    => ReachFormIntegration::class,
        ContactForm7Integration::INTEGRATION_NAME => ContactForm7Integration::class,
        WpFormsLiteIntegration::INTEGRATION_NAME  => WpFormsLiteIntegration::class,
        ElementorIntegration::INTEGRATION_NAME    => ElementorIntegration::class,
        WooCommerceIntegration::INTEGRATION_NAME  => WooCommerceIntegration::class,
        NinjaFormsIntegration::INTEGRATION_NAME   => NinjaFormsIntegration::class,
        SureFormsIntegration::INTEGRATION_NAME    => SureFormsIntegration::class,
        WSFormsIntegration::INTEGRATION_NAME      => WSFormsIntegration::class,
        ForminatorIntegration::INTEGRATION_NAME   => ForminatorIntegration::class,
        ThriveLeadsIntegration::INTEGRATION_NAME  => ThriveLeadsIntegration::class,
        BraveIntegration::INTEGRATION_NAME        => BraveIntegration::class,
        OptInMonsterIntegration::INTEGRATION_NAME => OptInMonsterIntegration::class,
    );

    public function register( Container $container ): void {

        $integrations = array(
            ReachFormIntegration::class    => array(
                $container->get( FormRepository::class ),
                $container->get( ContactListRepository::class ),
                $container->get( Functions::class ),
                $container->get( ReachFormBuilder::class ),
            ),
            ContactForm7Integration::class => array(),
            WpFormsLiteIntegration::class  => array(),
            ElementorIntegration::class    => array(
                $container->get( FormRepository::class ),
                $container->get( ReachApiHandler::class ),
            ),
            WooCommerceIntegration::class  => array(
                $container->get( FormRepository::class ),
            ),
            NinjaFormsIntegration::class   => array(),
            SureFormsIntegration::class    => array(),
            WSFormsIntegration::class      => array(),
            ForminatorIntegration::class   => array(),
            ThriveLeadsIntegration::class  => array(),
            BraveIntegration::class        => array(),
            OptInMonsterIntegration::class => array(
                $container->get( Functions::class ),
            ),
        );

        foreach ( $integrations as $class_name => $dependencies ) {
            $integration = new $class_name( ...$dependencies );
            $container->set(
                $integration::class,
                function () use ( $integration ) {
                    return $integration;
                }
            );

            $integration = $container->get( $integration::class );
            $integration->init();
        }

        $container->get( IntegrationsApiHandler::class )->init_hooks();
    }
}
