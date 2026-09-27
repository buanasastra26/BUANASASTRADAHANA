<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Contracts\SeoProvider;
use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\SeoData;

class AioseoProvider implements SeoProvider {
    private const MODEL_CLASS = '\AIOSEO\Plugin\Common\Models\Post';

    private const FOCUS_KEYWORD_MAX = 255;

    private const TWITTER_USE_OG_OPTION = 'useOgData';

    private const COLUMN_MAP = array(
        'seo_title'           => 'title',
        'meta_description'    => 'description',
        'canonical'           => 'canonical_url',
        'og_title'            => 'og_title',
        'og_description'      => 'og_description',
        'og_image'            => 'og_image_custom_url',
        'twitter_title'       => 'twitter_title',
        'twitter_description' => 'twitter_description',
        'twitter_image'       => 'twitter_image_custom_url',
    );

    private AioseoRobots $robots;

    public function __construct( ?AioseoRobots $robots = null ) {
        $this->robots = $robots ?? new AioseoRobots();
    }

    public function get_key(): string {
        return 'aioseo';
    }

    public function get_label(): string {
        return __( 'All in One SEO', 'hostinger-ai-assistant' );
    }

    public function is_active(): bool {
        return function_exists( 'aioseo' ) && class_exists( self::MODEL_CLASS );
    }

    public function supported_fields(): array {
        return array_merge( array_keys( self::COLUMN_MAP ), array( 'focus_keyword', 'robots' ) );
    }

    public function read( int $post_id ): SeoData {
        $data = new SeoData();

        if ( ! $this->is_active() ) {
            return $data;
        }

        $record = $this->fetch_record( $post_id );

        if ( ! $record ) {
            return $data;
        }

        foreach ( self::COLUMN_MAP as $field => $column ) {
            $value = $record->$column ?? '';

            if ( is_string( $value ) && $value !== '' ) {
                $data->set( $field, $value );
            }
        }

        $keyphrase = $this->read_focus_keyword( $record );

        if ( $keyphrase !== '' ) {
            $data->set( 'focus_keyword', $keyphrase );
        }

        $data->set( 'robots', $this->robots->read( $record ) );

        return $data;
    }

    public function write( int $post_id, SeoData $data ): array {
        if ( ! $this->is_active() ) {
            return array(
                'written' => array(),
                'skipped' => $data->fields(),
            );
        }

        $record   = $this->fetch_record( $post_id );
        $written  = array();
        $skipped  = array();
        $payload  = array();
        $expected = null;

        foreach ( $data->fields() as $field ) {
            if ( isset( self::COLUMN_MAP[ $field ] ) ) {
                $payload[ self::COLUMN_MAP[ $field ] ] = $data->get( $field );
                $written[]                             = $field;
                continue;
            }

            if ( $field === 'focus_keyword' ) {
                $payload   = array_merge( $payload, $this->keyphrase_payload( $record, (string) $data->get( $field ) ) );
                $written[] = $field;
                continue;
            }

            if ( $field === 'robots' ) {
                $robots = $data->get( 'robots' );

                if ( ! is_array( $robots ) ) {
                    $skipped[] = $field;
                    continue;
                }

                $fragment = $this->robots->payload( $record, $robots );

                if ( $fragment['default'] !== true && in_array( SeoData::ROBOTS_INHERIT, $robots, true ) ) {
                    $skipped[] = $field;
                    continue;
                }

                $expected  = $this->robots->expected( $fragment );
                $payload   = array_merge( $payload, $fragment );
                $written[] = $field;
                continue;
            }

            $skipped[] = $field;
        }

        $payload = array_merge( $payload, $this->image_type_payload( $data ) );

        if ( empty( $payload ) ) {
            return array(
                'written' => array_values( $written ),
                'skipped' => $skipped,
            );
        }

        $error = $this->store_record( $post_id, $payload );

        if ( is_string( $error ) && $error !== '' ) {
            return array(
                'written' => array(),
                'skipped' => array_values( array_merge( $skipped, $written ) ),
            );
        }

        clean_post_cache( $post_id );

        $persisted = $this->read( $post_id );

        foreach ( $written as $index => $field ) {
            if ( $field === 'robots' ) {
                if ( $persisted->get( 'robots' ) !== $expected ) {
                    unset( $written[ $index ] );
                    $skipped[] = $field;
                }
                continue;
            }

            if ( $data->get( $field ) !== '' && ! $persisted->has( $field ) ) {
                unset( $written[ $index ] );
                $skipped[] = $field;
            }
        }

        return array(
            'written' => array_values( $written ),
            'skipped' => $skipped,
        );
    }

    /**
     * @return array<string, bool|string>
     */
    private function image_type_payload( SeoData $data ): array {
        $payload = array();

        if ( $data->has( 'og_image' ) ) {
            $payload['og_image_type'] = (string) $data->get( 'og_image' ) !== '' ? 'custom_image' : 'default';
        }

        if ( $data->has( 'twitter_image' ) ) {
            if ( (string) $data->get( 'twitter_image' ) !== '' ) {
                $payload['twitter_use_og']     = false;
                $payload['twitter_image_type'] = 'custom_image';
            } else {
                $payload['twitter_image_type'] = 'default';
                $payload['twitter_use_og']     = $this->site_twitter_uses_og();
            }
        }

        return $payload;
    }

    private function site_twitter_uses_og(): bool {
        if ( ! function_exists( 'aioseo' ) ) {
            return false;
        }

        $options = aioseo()->options->social->twitter->general ?? null;
        $setting = self::TWITTER_USE_OG_OPTION;

        return (bool) ( $options->$setting ?? false );
    }

    /**
     * @return array<string, mixed>
     */
    private function keyphrase_payload( ?object $record, string $keyphrase ): array {
        $keyphrases = $record ? $this->decode_keyphrases( $record ) : array();

        if ( ! isset( $keyphrases['focus'] ) || ! is_array( $keyphrases['focus'] ) ) {
            $keyphrases['focus'] = array();
        }

        $keyphrase = mb_substr( $keyphrase, 0, self::FOCUS_KEYWORD_MAX );

        $keyphrases['focus']['keyphrase'] = $keyphrase;

        return array(
            'keyphrases'    => $keyphrases,
            'focus_keyword' => $keyphrase === '' ? null : $keyphrase,
        );
    }

    private function read_focus_keyword( object $record ): string {
        $column = $record->focus_keyword ?? '';

        if ( is_string( $column ) && $column !== '' ) {
            return $column;
        }

        $keyphrases = $this->decode_keyphrases( $record );
        $keyphrase  = $keyphrases['focus']['keyphrase'] ?? '';

        return is_scalar( $keyphrase ) ? (string) $keyphrase : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function decode_keyphrases( object $record ): array {
        $keyphrases = $record->keyphrases ?? '';

        if ( is_string( $keyphrases ) ) {
            $keyphrases = json_decode( $keyphrases, true );
        }

        if ( is_object( $keyphrases ) ) {
            $keyphrases = json_decode( (string) wp_json_encode( $keyphrases ), true );
        }

        return is_array( $keyphrases ) ? $keyphrases : array();
    }

    protected function fetch_record( int $post_id ): ?object {
        $record = call_user_func( array( self::MODEL_CLASS, 'getPost' ), $post_id );

        return is_object( $record ) ? $record : null;
    }

    /**
     * @return mixed
     */
    protected function store_record( int $post_id, array $payload ) {
        return call_user_func( array( self::MODEL_CLASS, 'savePost' ), $post_id, $payload );
    }
}
