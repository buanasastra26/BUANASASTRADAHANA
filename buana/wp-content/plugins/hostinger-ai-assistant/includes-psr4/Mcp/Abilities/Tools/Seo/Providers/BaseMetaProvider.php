<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers;

use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Contracts\SeoProvider;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\SeoData;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

abstract class BaseMetaProvider implements SeoProvider {
    /**
     * @return array<string, string> Normalized field name => post meta key.
     */
    abstract protected function meta_map(): array;

    /**
     * @return array<string, bool|string>
     */
    abstract protected function read_robots( int $post_id ): array;

    abstract protected function write_robots( int $post_id, array $robots ): void;

    public function supported_fields(): array {
        return array_merge( array_keys( $this->meta_map() ), array( 'robots' ) );
    }

    public function read( int $post_id ): SeoData {
        $data = new SeoData();

        foreach ( $this->meta_map() as $field => $meta_key ) {
            $value = get_post_meta( $post_id, $meta_key, true );

            if ( ! is_string( $value ) || $value === '' ) {
                continue;
            }

            $data->set( $field, $value );
        }

        $data->set( 'robots', $this->read_robots( $post_id ) );

        return $data;
    }

    public function write( int $post_id, SeoData $data ): array {
        $written = array();
        $skipped = array();
        $map     = $this->meta_map();

        foreach ( $data->fields() as $field ) {
            if ( $field === 'robots' ) {
                if ( is_array( $data->get( 'robots' ) ) ) {
                    $this->write_robots( $post_id, $data->get( 'robots' ) );
                    $written[] = $field;
                } else {
                    $skipped[] = $field;
                }
                continue;
            }

            if ( ! isset( $map[ $field ] ) ) {
                $skipped[] = $field;
                continue;
            }

            if ( $this->rejects_field( $post_id, $field, $data->get( $field ) ) ) {
                $skipped[] = $field;
                continue;
            }

            update_post_meta( $post_id, $map[ $field ], $this->prepare_value( $post_id, $field, $data->get( $field ) ) );
            $written[] = $field;
        }

        if ( ! empty( $written ) ) {
            $verified = $this->read( $post_id );

            foreach ( $written as $index => $field ) {
                if ( $field === 'robots' || $data->get( $field ) === '' ) {
                    continue;
                }

                if ( ! $verified->has( $field ) ) {
                    unset( $written[ $index ] );
                    $skipped[] = $field;
                }
            }

            $written = array_values( $written );
        }

        foreach ( $written as $field ) {
            if ( $field === 'robots' ) {
                continue;
            }

            $this->write_companion( $post_id, $field, (string) $data->get( $field ) );
        }

        if ( ! empty( $written ) ) {
            $this->after_write( $post_id, $written );
        }

        return array(
            'written' => $written,
            'skipped' => $skipped,
        );
    }

    protected function prepare_value( int $post_id, string $field, $value ) {
        return $value;
    }

    protected function write_companion( int $post_id, string $field, string $value ): void {
    }

    protected function rejects_field( int $post_id, string $field, $value ): bool {
        return false;
    }

    protected function after_write( int $post_id, array $written ): void {
    }

    protected function default_robots(): array {
        return array(
            'noindex'   => SeoData::ROBOTS_INHERIT,
            'nofollow'  => SeoData::ROBOTS_INHERIT,
            'noarchive' => SeoData::ROBOTS_INHERIT,
        );
    }
}
