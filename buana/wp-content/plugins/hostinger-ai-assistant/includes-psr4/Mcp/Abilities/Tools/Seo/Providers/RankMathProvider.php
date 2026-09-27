<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\SeoData;

class RankMathProvider extends BaseMetaProvider {
    private const ROBOTS_META_KEY = 'rank_math_robots';

    private const IMAGE_ID_META_KEYS = array(
        'og_image'      => 'rank_math_facebook_image_id',
        'twitter_image' => 'rank_math_twitter_image_id',
    );

    private const TWITTER_USE_FACEBOOK_META_KEY = 'rank_math_twitter_use_facebook';

    private array $attachment_ids = array();

    public function get_key(): string {
        return 'rank_math';
    }

    public function get_label(): string {
        return __( 'Rank Math SEO', 'hostinger-ai-assistant' );
    }

    public function is_active(): bool {
        return defined( 'RANK_MATH_VERSION' );
    }

    protected function meta_map(): array {
        return array(
            'seo_title'           => 'rank_math_title',
            'meta_description'    => 'rank_math_description',
            'canonical'           => 'rank_math_canonical_url',
            'focus_keyword'       => 'rank_math_focus_keyword',
            'og_title'            => 'rank_math_facebook_title',
            'og_description'      => 'rank_math_facebook_description',
            'og_image'            => 'rank_math_facebook_image',
            'twitter_title'       => 'rank_math_twitter_title',
            'twitter_description' => 'rank_math_twitter_description',
            'twitter_image'       => 'rank_math_twitter_image',
        );
    }

    protected function read_robots( int $post_id ): array {
        $stored = $this->read_directives( $post_id );

        if ( empty( $stored ) ) {
            return $this->default_robots();
        }

        return array(
            'noindex'   => in_array( 'noindex', $stored, true ),
            'nofollow'  => in_array( 'nofollow', $stored, true ),
            'noarchive' => in_array( 'noarchive', $stored, true ),
        );
    }

    public function read( int $post_id ): SeoData {
        $data = parent::read( $post_id );

        if ( $data->has( 'focus_keyword' ) ) {
            $primary = $this->primary_keyword( (string) $data->get( 'focus_keyword' ) );

            if ( $primary === '' ) {
                return $this->without_focus_keyword( $data );
            }

            $data->set( 'focus_keyword', $primary );
        }

        return $data;
    }

    protected function prepare_value( int $post_id, string $field, $value ) {
        if ( $field !== 'focus_keyword' || $value === '' ) {
            return $value;
        }

        $existing = $this->keyword_list( (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ) );
        $tail     = array_values( array_diff( array_slice( $existing, 1 ), array( (string) $value ) ) );

        return implode( ', ', array_merge( array( (string) $value ), $tail ) );
    }

    private function primary_keyword( string $stored ): string {
        $keywords = $this->keyword_list( $stored );

        return $keywords[0] ?? '';
    }

    private function keyword_list( string $stored ): array {
        return array_values( array_filter( array_map( 'trim', explode( ',', $stored ) ) ) );
    }

    private function without_focus_keyword( SeoData $data ): SeoData {
        $values = $data->to_array();
        unset( $values['focus_keyword'] );

        $clean = new SeoData();

        foreach ( $values as $field => $value ) {
            $clean->set( $field, $value );
        }

        return $clean;
    }

    protected function write_robots( int $post_id, array $robots ): void {
        $directives = $this->read_directives( $post_id );
        $positives  = array(
            'noindex'   => 'index',
            'nofollow'  => 'follow',
            'noarchive' => null,
        );

        foreach ( $positives as $negative => $positive ) {
            if ( ! array_key_exists( $negative, $robots ) ) {
                continue;
            }

            $directives = $this->apply_directive( $directives, $negative, $positive, $robots[ $negative ] );
        }

        update_post_meta( $post_id, self::ROBOTS_META_KEY, array_values( array_unique( $directives ) ) );
    }

    protected function rejects_field( int $post_id, string $field, $value ): bool {
        if ( ! isset( self::IMAGE_ID_META_KEYS[ $field ] ) ) {
            return false;
        }

        if ( (string) $value === '' ) {
            return false;
        }

        return ! $this->attachment_id_for( (string) $value );
    }

    private function attachment_id_for( string $url ): int {
        if ( ! array_key_exists( $url, $this->attachment_ids ) ) {
            $this->attachment_ids[ $url ] = attachment_url_to_postid( $url );
        }

        return $this->attachment_ids[ $url ];
    }

    protected function write_companion( int $post_id, string $field, string $value ): void {
        if ( isset( self::IMAGE_ID_META_KEYS[ $field ] ) ) {
            $meta_key = self::IMAGE_ID_META_KEYS[ $field ];

            if ( $value === '' ) {
                delete_post_meta( $post_id, $meta_key );
                return;
            }

            $attachment_id = $this->attachment_id_for( $value );

            if ( $attachment_id ) {
                update_post_meta( $post_id, $meta_key, (string) $attachment_id );
            }

            return;
        }

        if ( in_array( $field, array( 'twitter_title', 'twitter_description', 'twitter_image' ), true ) ) {
            update_post_meta( $post_id, self::TWITTER_USE_FACEBOOK_META_KEY, 'off' );
        }
    }

    protected function after_write( int $post_id, array $written ): void {
        clean_post_cache( $post_id );
    }

    private function read_directives( int $post_id ): array {
        $stored = get_post_meta( $post_id, self::ROBOTS_META_KEY, true );

        if ( ! is_array( $stored ) ) {
            return array();
        }

        return array_values( array_filter( $stored, 'is_string' ) );
    }

    private function apply_directive( array $directives, string $negative, ?string $positive, $state ): array {
        $remove = array( $negative );

        if ( $positive !== null ) {
            $remove[] = $positive;
        }

        $directives = array_diff( $directives, $remove );

        if ( $state === SeoData::ROBOTS_INHERIT ) {
            return $directives;
        }

        if ( $state === true ) {
            $directives[] = $negative;
        } elseif ( $state === false && $positive !== null ) {
            $directives[] = $positive;
        }

        return $directives;
    }
}
