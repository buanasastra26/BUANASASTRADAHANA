<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo;

use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Contracts\SeoProvider;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers\AioseoProvider;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers\HostingerProvider;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers\RankMathProvider;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers\YoastProvider;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ProviderResolver {
    public const FALLBACK_KEY = 'hostinger';

    /**
     * @var SeoProvider[] In read-priority order.
     */
    private array $providers;

    /**
     * @var array<string, bool>
     */
    private array $active = array();

    private ?SeoProvider $fallback = null;

    /**
     * @param SeoProvider[]|null $providers
     */
    public function __construct( ?array $providers = null ) {
        $this->providers = $providers ?? array(
            new YoastProvider(),
            new RankMathProvider(),
            new AioseoProvider(),
            new HostingerProvider(),
        );
    }

    public function get_primary(): SeoProvider {
        foreach ( $this->providers as $provider ) {
            if ( $this->is_active( $provider ) ) {
                return $provider;
            }
        }

        return $this->get_fallback();
    }

    private function is_active( SeoProvider $provider ): bool {
        $key = $provider->get_key();

        if ( ! array_key_exists( $key, $this->active ) ) {
            $this->active[ $key ] = $provider->is_active();
        }

        return $this->active[ $key ];
    }

    /**
     * @return SeoProvider[]
     */
    public function get_writable(): array {
        $plugin_providers = array();

        foreach ( $this->providers as $provider ) {
            if ( $provider->get_key() === self::FALLBACK_KEY ) {
                continue;
            }

            if ( $this->is_active( $provider ) ) {
                $plugin_providers[] = $provider;
            }
        }

        if ( ! empty( $plugin_providers ) ) {
            return $plugin_providers;
        }

        return array( $this->get_fallback() );
    }

    /**
     * @return string[]
     */
    public function get_active_keys(): array {
        $keys = array();

        foreach ( $this->providers as $provider ) {
            if ( $provider->get_key() === self::FALLBACK_KEY ) {
                continue;
            }

            if ( $this->is_active( $provider ) ) {
                $keys[] = $provider->get_key();
            }
        }

        return $keys;
    }

    /**
     * @return string[]
     */
    public function get_supported_fields(): array {
        $fields = array();

        foreach ( $this->get_writable() as $provider ) {
            $fields = array_merge( $fields, $provider->supported_fields() );
        }

        return array_values( array_unique( $fields ) );
    }

    public function get_fallback(): SeoProvider {
        if ( $this->fallback instanceof SeoProvider ) {
            return $this->fallback;
        }

        foreach ( $this->providers as $provider ) {
            if ( $provider->get_key() === self::FALLBACK_KEY ) {
                $this->fallback = $provider;

                return $this->fallback;
            }
        }

        $this->fallback = new HostingerProvider();

        return $this->fallback;
    }
}
