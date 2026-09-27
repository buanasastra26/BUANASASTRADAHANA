<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class SeoData {
    public const TEXT_FIELDS = array(
        'seo_title',
        'meta_description',
        'focus_keyword',
        'og_title',
        'og_description',
        'twitter_title',
        'twitter_description',
    );

    public const URL_FIELDS = array(
        'canonical',
        'og_image',
        'twitter_image',
    );

    public const ROBOTS_DIRECTIVES = array(
        'noindex',
        'nofollow',
        'noarchive',
    );

    public const ROBOTS_INHERIT = 'default';

    private array $values   = array();
    private array $rejected = array();

    public static function fields_list(): array {
        return array_merge( self::TEXT_FIELDS, self::URL_FIELDS, array( 'robots' ) );
    }

    public static function from_array( array $input ): self {
        $data = new self();

        foreach ( self::TEXT_FIELDS as $field ) {
            if ( ! array_key_exists( $field, $input ) || $input[ $field ] === null ) {
                continue;
            }

            if ( ! is_scalar( $input[ $field ] ) ) {
                $data->rejected[] = $field;
                continue;
            }

            $data->values[ $field ] = sanitize_text_field( (string) $input[ $field ] );
        }

        foreach ( self::URL_FIELDS as $field ) {
            if ( ! array_key_exists( $field, $input ) || $input[ $field ] === null ) {
                continue;
            }

            if ( ! is_scalar( $input[ $field ] ) ) {
                $data->rejected[] = $field;
                continue;
            }

            $raw    = (string) $input[ $field ];
            $parsed = self::sanitize_url( $raw );

            if ( $raw !== '' && $parsed === '' ) {
                $data->rejected[] = $field;
                continue;
            }

            $data->values[ $field ] = $parsed;
        }

        if ( array_key_exists( 'robots', $input ) && $input['robots'] !== null ) {
            if ( ! is_array( $input['robots'] ) ) {
                $data->rejected[] = 'robots';
            } else {
                $robots   = array();
                $rejected = false;

                foreach ( self::ROBOTS_DIRECTIVES as $directive ) {
                    if ( ! array_key_exists( $directive, $input['robots'] ) || $input['robots'][ $directive ] === null ) {
                        continue;
                    }

                    $value = $input['robots'][ $directive ];

                    if ( is_string( $value ) && strtolower( $value ) === self::ROBOTS_INHERIT ) {
                        $robots[ $directive ] = self::ROBOTS_INHERIT;
                        continue;
                    }

                    if ( ! is_scalar( $value ) ) {
                        $rejected = true;
                        break;
                    }

                    $robots[ $directive ] = ! empty( $value );
                }

                if ( $rejected ) {
                    $data->rejected[] = 'robots';
                } elseif ( ! empty( $robots ) ) {
                    $data->values['robots'] = $robots;
                }
            }
        }

        return $data;
    }

    private static function sanitize_url( string $raw ): string {
        if ( $raw === '' ) {
            return '';
        }

        $scheme = wp_parse_url( $raw, PHP_URL_SCHEME );

        if ( ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) ) {
            return '';
        }

        $url  = esc_url_raw( $raw );
        $host = $url === '' ? null : wp_parse_url( $url, PHP_URL_HOST );

        if ( ! is_string( $host ) || ! preg_match( '/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $host ) ) {
            return '';
        }

        return $url;
    }

    public function has( string $field ): bool {
        return array_key_exists( $field, $this->values );
    }

    public function get( string $field ) {
        return $this->values[ $field ] ?? null;
    }

    public function set( string $field, $value ): void {
        if ( ! in_array( $field, self::fields_list(), true ) ) {
            return;
        }

        $this->values[ $field ] = $value;
    }

    public function fields(): array {
        return array_keys( $this->values );
    }

    public function to_array(): array {
        return $this->values;
    }

    public function is_empty(): bool {
        return empty( $this->values );
    }

    public function rejected_fields(): array {
        return array_values( array_unique( $this->rejected ) );
    }
}
