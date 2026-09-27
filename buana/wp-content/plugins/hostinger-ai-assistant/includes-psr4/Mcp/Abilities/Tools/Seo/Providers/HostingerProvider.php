<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

use Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\SeoData;

class HostingerProvider extends BaseMetaProvider {
    private const ROBOTS_META_KEY = 'hostinger_ai_assistant_seo_robots';

    /**
     * @var array<string, string> Normalized field name => Hostinger AI theme post meta key.
     */
    private const THEME_META_MAP = array(
        'seo_title'        => 'hostinger_ai_post_meta_title',
        'meta_description' => 'hostinger_ai_post_meta_description',
    );

    public function get_key(): string {
        return 'hostinger';
    }

    public function get_label(): string {
        return __( 'Hostinger AI Assistant', 'hostinger-ai-assistant' );
    }

    public function is_active(): bool {
        return true;
    }

    public function read( int $post_id ): SeoData {
        $data = parent::read( $post_id );

        foreach ( self::THEME_META_MAP as $field => $meta_key ) {
            if ( $data->has( $field ) ) {
                continue;
            }

            $value = get_post_meta( $post_id, $meta_key, true );

            if ( ! is_string( $value ) || $value === '' ) {
                continue;
            }

            $data->set( $field, $value );
        }

        return $data;
    }

    protected function meta_map(): array {
        return array(
            'seo_title'           => 'hostinger_ai_assistant_seo_title',
            'meta_description'    => 'hostinger_ai_assistant_seo_description',
            'focus_keyword'       => 'hostinger_ai_assistant_seo_focus_keyword',
            'canonical'           => 'hostinger_ai_assistant_seo_canonical',
            'og_title'            => 'hostinger_ai_assistant_seo_og_title',
            'og_description'      => 'hostinger_ai_assistant_seo_og_description',
            'og_image'            => 'hostinger_ai_assistant_seo_og_image',
            'twitter_title'       => 'hostinger_ai_assistant_seo_twitter_title',
            'twitter_description' => 'hostinger_ai_assistant_seo_twitter_description',
            'twitter_image'       => 'hostinger_ai_assistant_seo_twitter_image',
        );
    }

    protected function read_robots( int $post_id ): array {
        $stored = get_post_meta( $post_id, self::ROBOTS_META_KEY, true );

        if ( ! is_array( $stored ) ) {
            return $this->default_robots();
        }

        $robots = $this->default_robots();

        foreach ( array_keys( $robots ) as $directive ) {
            if ( ! array_key_exists( $directive, $stored ) ) {
                continue;
            }

            $robots[ $directive ] = $stored[ $directive ] === SeoData::ROBOTS_INHERIT
                ? SeoData::ROBOTS_INHERIT
                : (bool) $stored[ $directive ];
        }

        return $robots;
    }

    protected function write_robots( int $post_id, array $robots ): void {
        $merged = array_intersect_key(
            array_merge( $this->read_robots( $post_id ), $robots ),
            $this->default_robots()
        );

        update_post_meta( $post_id, self::ROBOTS_META_KEY, $merged );
    }
}
