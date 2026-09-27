<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Constants\GenerationConstant;
use WP_Term;

defined( 'ABSPATH' ) || exit;

class EnvelopePosts {

    private MediaSideloader $media;
    private Seo $seo;
    private AffiliateBuilder $affiliate_builder;

    private array $created_category_ids = array();

    private ?int $post_author = null;

    public function __construct(
        ?MediaSideloader $media = null,
        ?Seo $seo = null,
        ?AffiliateBuilder $affiliate_builder = null
    ) {
        $this->media             = $media ?? new MediaSideloader();
        $this->seo               = $seo ?? new Seo();
        $this->affiliate_builder = $affiliate_builder ?? new AffiliateBuilder();
    }

    public function create( array $blog, array $media_map ): array {
        $posts = $blog['posts'] ?? array();

        if ( empty( $posts ) || ! is_array( $posts ) ) {
            return array(
                'post_ids'     => array(),
                'category_ids' => array(),
            );
        }

        $term_id_by_name = $this->ensure_categories( $blog, $posts );
        $created_ids     = array();

        foreach ( $posts as $post ) {
            if ( empty( $post['title'] ) ) {
                continue;
            }

            $post_id = $this->create_post( $post, $media_map, $term_id_by_name );

            if ( $post_id > 0 ) {
                $created_ids[] = $post_id;
            }
        }

        return array(
            'post_ids'     => $created_ids,
            'category_ids' => array_values( array_unique( $this->created_category_ids ) ),
        );
    }

    private function ensure_categories( array $blog, array $posts ): array {
        $names = array();

        foreach ( $blog['categories'] ?? array() as $category ) {
            if ( ! empty( $category['name'] ) ) {
                $names[] = (string) $category['name'];
            }
        }

        foreach ( $posts as $post ) {
            foreach ( $post['categories'] ?? array() as $category ) {
                if ( ! empty( $category['name'] ) ) {
                    $names[] = (string) $category['name'];
                }
            }
        }

        $names = array_values( array_unique( $names ) );
        $map   = array();

        foreach ( $names as $name ) {
            $term = get_term_by( 'name', $name, 'category' );

            if ( ! $term instanceof WP_Term ) {
                $result = wp_insert_term( $name, 'category' );
                if ( is_wp_error( $result ) || empty( $result['term_id'] ) ) {
                    continue;
                }

                $term_id                        = (int) $result['term_id'];
                $this->created_category_ids[]   = $term_id;
                $map[ $name ]                   = $term_id;
                continue;
            }

            $map[ $name ] = (int) $term->term_id;
        }

        return $map;
    }

    private function create_post( array $post, array $media_map, array $term_id_by_name ): int {
        $blocks  = is_array( $post['content'] ?? null ) ? $post['content'] : array();
        $blocks  = $this->media->rewrite_gutenberg( $blocks, $media_map );
        $content = serialize_blocks( $blocks ) . $this->generate_affiliate_shortcode( $post );

        $post_data = array(
            'post_title'   => sanitize_text_field( (string) $post['title'] ),
            'post_content' => $content,
            'post_excerpt' => wp_kses_post( (string) ( $post['excerpt'] ?? '' ) ),
            'post_status'  => 'publish',
            'post_type'    => 'post',
            'post_author'  => $this->resolve_post_author(),
            'meta_input'   => array(
                GenerationConstant::META_KEY => '1',
            ),
        );

        if ( ! empty( $post['slug'] ) ) {
            $post_data['post_name'] = sanitize_title( (string) $post['slug'] );
        }

        $post_id = Helper::insert_trusted_post( $post_data );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return 0;
        }

        $post_id = (int) $post_id;

        $this->attach_featured_image( $post_id, $post, $media_map );
        $this->assign_categories( $post_id, $post, $term_id_by_name );
        $this->apply_seo( $post_id, $post );

        return $post_id;
    }

    private function generate_affiliate_shortcode( array $post ): string {
        $keyword = $this->get_affiliate_keyword( $post );

        if ( $keyword === '' ) {
            return '';
        }

        return $this->affiliate_builder->generate_shortcode( $keyword );
    }

    private function get_affiliate_keyword( array $post ): string {
        foreach ( $post['tags'] ?? array() as $tag ) {
            $name = is_array( $tag ) ? ( $tag['name'] ?? '' ) : $tag;

            if ( is_string( $name ) && $name !== '' ) {
                return sanitize_text_field( $name );
            }
        }

        foreach ( $post['categories'] ?? array() as $category ) {
            $name = is_array( $category ) ? ( $category['name'] ?? '' ) : $category;

            if ( is_string( $name ) && $name !== '' ) {
                return sanitize_text_field( $name );
            }
        }

        return sanitize_text_field( (string) ( $post['title'] ?? '' ) );
    }

    private function resolve_post_author(): int {
        if ( $this->post_author !== null ) {
            return $this->post_author;
        }

        $author = get_current_user_id();

        if ( $author < 1 ) {
            $author = Helper::oldest_administrator_id();
        }

        $this->post_author = (int) $author;

        return $this->post_author;
    }

    private function attach_featured_image( int $post_id, array $post, array $media_map ): void {
        $featured = $post['featured_image'] ?? null;
        if ( ! is_array( $featured ) ) {
            return;
        }

        $src = $featured['src'] ?? '';
        if ( is_string( $src ) && isset( $media_map[ $src ] ) ) {
            set_post_thumbnail( $post_id, (int) $media_map[ $src ]['id'] );
        }
    }

    private function assign_categories( int $post_id, array $post, array $term_id_by_name ): void {
        $term_ids = array();

        foreach ( $post['categories'] ?? array() as $category ) {
            $name = $category['name'] ?? '';
            if ( is_string( $name ) && isset( $term_id_by_name[ $name ] ) ) {
                $term_ids[] = $term_id_by_name[ $name ];
            }
        }

        if ( ! empty( $term_ids ) ) {
            wp_set_object_terms( $post_id, array_values( array_unique( $term_ids ) ), 'category', false );
        }
    }

    private function apply_seo( int $post_id, array $post ): void {
        $this->seo->load_seo_title( $post_id, (string) $post['title'] );

        if ( ! empty( $post['excerpt'] ) ) {
            $this->seo->load_seo_description( $post_id, (string) $post['excerpt'] );
        }

        $this->seo->add_seo_meta_tags( $post_id );
    }
}
