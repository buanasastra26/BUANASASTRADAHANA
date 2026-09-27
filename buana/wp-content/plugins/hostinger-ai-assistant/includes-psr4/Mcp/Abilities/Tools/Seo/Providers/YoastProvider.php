<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\SeoData;

class YoastProvider extends BaseMetaProvider {
    private const NOINDEX_META_KEY   = '_yoast_wpseo_meta-robots-noindex';
    private const NOFOLLOW_META_KEY  = '_yoast_wpseo_meta-robots-nofollow';
    private const ADVANCED_META_KEY  = '_yoast_wpseo_meta-robots-adv';
    private const INHERIT_META_VALUE = '0';

    private const IMAGE_ID_META_KEYS = array(
        'og_image'      => '_yoast_wpseo_opengraph-image-id',
        'twitter_image' => '_yoast_wpseo_twitter-image-id',
    );

    public function get_key(): string {
        return 'yoast';
    }

    public function get_label(): string {
        return __( 'Yoast SEO', 'hostinger-ai-assistant' );
    }

    public function is_active(): bool {
        return defined( 'WPSEO_VERSION' );
    }

    protected function meta_map(): array {
        return array(
            'seo_title'           => '_yoast_wpseo_title',
            'meta_description'    => '_yoast_wpseo_metadesc',
            'canonical'           => '_yoast_wpseo_canonical',
            'focus_keyword'       => '_yoast_wpseo_focuskw',
            'og_title'            => '_yoast_wpseo_opengraph-title',
            'og_description'      => '_yoast_wpseo_opengraph-description',
            'og_image'            => '_yoast_wpseo_opengraph-image',
            'twitter_title'       => '_yoast_wpseo_twitter-title',
            'twitter_description' => '_yoast_wpseo_twitter-description',
            'twitter_image'       => '_yoast_wpseo_twitter-image',
        );
    }

    protected function read_robots( int $post_id ): array {
        $advanced = $this->read_advanced_directives( $post_id );
        $noindex  = get_post_meta( $post_id, self::NOINDEX_META_KEY, true );
        $nofollow = get_post_meta( $post_id, self::NOFOLLOW_META_KEY, true );

        return array(
            'noindex'   => $this->read_tri_state( $noindex, '1', '2' ),
            'nofollow'  => $nofollow === '1',
            'noarchive' => in_array( 'noarchive', $advanced, true ) ? true : SeoData::ROBOTS_INHERIT,
        );
    }

    private function read_tri_state( $stored, string $positive, string $negative ) {
        if ( $stored === $positive ) {
            return true;
        }

        if ( $stored === $negative ) {
            return false;
        }

        return SeoData::ROBOTS_INHERIT;
    }

    protected function write_robots( int $post_id, array $robots ): void {
        if ( array_key_exists( 'noindex', $robots ) ) {
            update_post_meta( $post_id, self::NOINDEX_META_KEY, $this->write_tri_state( $robots['noindex'], '1', '2' ) );
        }

        if ( array_key_exists( 'nofollow', $robots ) ) {
            update_post_meta( $post_id, self::NOFOLLOW_META_KEY, $this->write_tri_state( $robots['nofollow'], '1', '0' ) );
        }

        if ( ! array_key_exists( 'noarchive', $robots ) ) {
            return;
        }

        $advanced = $this->read_advanced_directives( $post_id );
        $advanced = array_values( array_diff( $advanced, array( 'noarchive' ) ) );

        if ( $robots['noarchive'] === true ) {
            $advanced[] = 'noarchive';
        }

        update_post_meta( $post_id, self::ADVANCED_META_KEY, implode( ',', $advanced ) );
    }

    private function write_tri_state( $value, string $positive, string $negative ): string {
        if ( $value === SeoData::ROBOTS_INHERIT ) {
            return self::INHERIT_META_VALUE;
        }

        return $value ? $positive : $negative;
    }

    protected function write_companion( int $post_id, string $field, string $value ): void {
        if ( ! isset( self::IMAGE_ID_META_KEYS[ $field ] ) ) {
            return;
        }

        $meta_key = self::IMAGE_ID_META_KEYS[ $field ];

        if ( $value === '' ) {
            delete_post_meta( $post_id, $meta_key );
            return;
        }

        $attachment_id = attachment_url_to_postid( $value );

        if ( $attachment_id ) {
            update_post_meta( $post_id, $meta_key, (string) $attachment_id );
        } else {
            delete_post_meta( $post_id, $meta_key );
        }
    }

    protected function after_write( int $post_id, array $written ): void {
        clean_post_cache( $post_id );
    }

    private function read_advanced_directives( int $post_id ): array {
        $stored = get_post_meta( $post_id, self::ADVANCED_META_KEY, true );

        if ( ! is_string( $stored ) || $stored === '' ) {
            return array();
        }

        return array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $stored ) ) ) ) );
    }
}
