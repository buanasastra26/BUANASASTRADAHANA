<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\SeoData;

class AioseoRobots {
    /**
     * @return array<string, bool|string>
     */
    public function read( object $record ): array {
        if ( ! empty( $record->robots_default ) ) {
            return array(
                'noindex'   => SeoData::ROBOTS_INHERIT,
                'nofollow'  => SeoData::ROBOTS_INHERIT,
                'noarchive' => SeoData::ROBOTS_INHERIT,
            );
        }

        return array(
            'noindex'   => ! empty( $record->robots_noindex ),
            'nofollow'  => ! empty( $record->robots_nofollow ),
            'noarchive' => ! empty( $record->robots_noarchive ),
        );
    }

    /**
     * @return array<string, bool>
     */
    public function payload( ?object $record, array $robots ): array {
        $current = $record ? $this->read( $record ) : array();
        $merged  = array();

        foreach ( SeoData::ROBOTS_DIRECTIVES as $directive ) {
            $merged[ $directive ] = array_key_exists( $directive, $robots )
                ? $robots[ $directive ]
                : ( $current[ $directive ] ?? SeoData::ROBOTS_INHERIT );
        }

        $explicit = array_filter(
            $merged,
            static function ( $state ): bool {
                return $state !== SeoData::ROBOTS_INHERIT;
            }
        );

        if ( empty( $explicit ) ) {
            return array( 'default' => true );
        }

        $payload = array( 'default' => false );

        foreach ( $merged as $directive => $state ) {
            $payload[ $directive ] = $state === SeoData::ROBOTS_INHERIT ? false : $state === true;
        }

        return $payload;
    }

    /**
     * @return array<string, bool|string>
     */
    public function expected( array $payload ): array {
        if ( $payload['default'] === true ) {
            return array(
                'noindex'   => SeoData::ROBOTS_INHERIT,
                'nofollow'  => SeoData::ROBOTS_INHERIT,
                'noarchive' => SeoData::ROBOTS_INHERIT,
            );
        }

        $expected = array();

        foreach ( SeoData::ROBOTS_DIRECTIVES as $directive ) {
            $expected[ $directive ] = $payload[ $directive ] === true;
        }

        return $expected;
    }
}
