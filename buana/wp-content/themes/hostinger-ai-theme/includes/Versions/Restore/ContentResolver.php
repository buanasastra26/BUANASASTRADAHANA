<?php

namespace Hostinger\AiTheme\Versions\Restore;

use Hostinger\AiTheme\Versions\VersionConstant;
use Hostinger\AiTheme\Versions\VersionSubject;
use WP_Term;

defined( 'ABSPATH' ) || exit;

class ContentResolver {
    private IdMap $map;
    private array $created_ids = array();

    public function __construct( IdMap $map ) {
        $this->map = $map;
    }

    public function get_created_ids(): array {
        return $this->created_ids;
    }

    public function resolve( object $snapshot_post, array $areas = array() ): int {
        // Trashed rows in a snapshot are leftovers from earlier stores, not part of the version's shop.
        if ( (string) $snapshot_post->post_status === 'trash' ) {
            return 0;
        }

        $role = (string) $snapshot_post->role;

        switch ( $role ) {
            case VersionConstant::ROLE_KIT:
                $live_id = $this->resolve_kit( $snapshot_post );
                break;

            case VersionConstant::ROLE_TEMPLATE_PART:
            case VersionConstant::ROLE_TEMPLATE:
                $live_id = $this->resolve_template( $snapshot_post, $areas );
                break;

            default:
                $live_id = $this->create_stub( $snapshot_post );
                break;
        }

        if ( $live_id <= 0 ) {
            return 0;
        }

        $snapshot_id = (int) $snapshot_post->post_id;

        if ( $snapshot_post->post_type === 'attachment' ) {
            $this->map->add_attachment( $snapshot_id, $live_id );
        } else {
            $this->map->add_post( $snapshot_id, $live_id );
        }

        $this->map->add_slug( (string) $snapshot_post->post_name, (string) get_post_field( 'post_name', $live_id ) );

        return $live_id;
    }

    private function resolve_kit( object $snapshot_post ): int {
        $kit_id = (int) get_option( VersionSubject::KIT_OPTION, 0 );
        if ( $kit_id > 0 && get_post_status( $kit_id ) ) {
            return $kit_id;
        }

        return $this->create_stub( $snapshot_post );
    }

    private function resolve_template( object $snapshot_post, array $areas ): int {
        $existing = $this->find_template( (string) $snapshot_post->post_name, (string) $snapshot_post->post_type, $areas );

        if ( $existing > 0 ) {
            return $existing;
        }

        return $this->create_stub( $snapshot_post );
    }

    private function find_template( string $post_name, string $post_type, array $areas ): int {
        if ( $post_name === '' ) {
            return 0;
        }

        $args = array(
            'post_type'        => $post_type,
            'post_status'      => 'any',
            'name'             => $post_name,
            'posts_per_page'   => 1,
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'suppress_filters' => false,
        );

        if ( $post_type === 'wp_template_part' && ! empty( $areas ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'wp_template_part_area',
                    'field'    => 'slug',
                    'terms'    => $areas,
                ),
            );
        }

        $found = get_posts( $args );

        return empty( $found ) ? 0 : (int) $found[0];
    }

    private function create_stub( object $snapshot_post ): int {
        $data = array(
            'post_type'      => (string) $snapshot_post->post_type,
            'post_status'    => (string) $snapshot_post->post_status,
            'post_title'     => (string) $snapshot_post->post_title,
            'post_name'      => (string) $snapshot_post->post_name,
            'post_author'    => (int) $snapshot_post->post_author,
            'post_date'      => (string) $snapshot_post->post_date,
            'post_date_gmt'  => (string) $snapshot_post->post_date_gmt,
            'post_excerpt'   => (string) $snapshot_post->post_excerpt,
            'post_password'  => (string) $snapshot_post->post_password,
            'comment_status' => (string) $snapshot_post->comment_status,
            'ping_status'    => (string) $snapshot_post->ping_status,
            'menu_order'     => (int) $snapshot_post->menu_order,
            'post_mime_type' => (string) $snapshot_post->post_mime_type,
            'post_content'   => '',
        );

        // wp_insert_post() unslashes what it is given, so slashed data goes in.
        $data = wp_slash( $data );

        if ( $snapshot_post->post_type === 'attachment' ) {
            $live_id = wp_insert_attachment( $data, false, 0, true );
        } else {
            $live_id = wp_insert_post( $data, true );
        }

        if ( is_wp_error( $live_id ) || ! $live_id ) {
            return 0;
        }

        $this->created_ids[] = (int) $live_id;

        return (int) $live_id;
    }

    public function resolve_term( object $snapshot_term ): int {
        $taxonomy = (string) $snapshot_term->taxonomy;
        $slug     = (string) $snapshot_term->slug;

        if ( $taxonomy === '' || ! taxonomy_exists( $taxonomy ) ) {
            return 0;
        }

        $existing = get_term_by( 'slug', $slug, $taxonomy );

        if ( $existing instanceof WP_Term ) {
            $this->map->add_term( (int) $snapshot_term->term_id, (int) $existing->term_id );

            return (int) $existing->term_id;
        }

        $created = wp_insert_term(
            (string) $snapshot_term->name,
            $taxonomy,
            array(
                'slug'        => $slug,
                'description' => (string) $snapshot_term->description,
            )
        );

        if ( is_wp_error( $created ) ) {
            return 0;
        }

        $term_id = (int) $created['term_id'];

        $this->map->add_term( (int) $snapshot_term->term_id, $term_id );

        return $term_id;
    }
}
