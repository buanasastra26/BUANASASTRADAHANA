<?php

namespace Hostinger\AiAssistant;

use Hostinger\AiAssistant\Providers\CliProvider;
use Hostinger\AiAssistant\Providers\ContainerProvider;
use Hostinger\AiAssistant\Providers\McpProvider;
use Hostinger\AiAssistant\Providers\NudgesProvider;
use Hostinger\AiAssistant\Providers\ProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class Boot {
    private Container $container;
    private array $providers = array(
        ContainerProvider::class,
        McpProvider::class,
        CliProvider::class,
        NudgesProvider::class,
    );

    private static ?Boot $instance = null;

    private function __construct() {
        $this->container = new Container();
    }

    public static function get_instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function plugins_loaded(): void {
        $this->register_providers();
    }

    private function register_providers(): void {
        foreach ( $this->providers as $provider_class ) {
            $provider = new $provider_class();
            if ( $provider instanceof ProviderInterface ) {
                $provider->register( $this->container );
            }
        }
    }
}
