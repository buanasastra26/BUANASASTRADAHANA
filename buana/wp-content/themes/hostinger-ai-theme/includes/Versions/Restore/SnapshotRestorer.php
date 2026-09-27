<?php

namespace Hostinger\AiTheme\Versions\Restore;

use Hostinger\AiTheme\Builder\AffiliateBuilder;
use Hostinger\AiTheme\Builder\Helper;
use Hostinger\AiTheme\Versions\VersionConstant;
use Hostinger\AiTheme\Versions\VersionRepository;
use Hostinger\AiTheme\Versions\VersionSubject;
use RuntimeException;
use Throwable;

defined( 'ABSPATH' ) || exit;

class SnapshotRestorer {
    public const CODE_RESTORE_FAILED = 'restore_failed';
    private VersionRepository $repository;
    private VersionSubject $subject;
    private IdMap $map;
    private ContentResolver $resolver;
    private ReferenceRewriter $rewriter;
    private array $trashed = array();

    public function __construct( ?VersionRepository $repository = null, ?VersionSubject $subject = null ) {
        $this->repository = $repository ?? new VersionRepository();
        $this->subject    = $subject ?? new VersionSubject();
        $this->map        = new IdMap();
        $this->resolver   = new ContentResolver( $this->map );
        $this->rewriter   = new ReferenceRewriter( $this->map );
    }

    public function get_map(): IdMap {
        return $this->map;
    }

    public function restore( int $version_id ): array {
        $version = $this->repository->get_version( $version_id );
        if ( $version === null ) {
            return $this->failure( __( 'Version not found.', 'hostinger-ai-theme' ) );
        }

        $previous_active = $this->repository->get_active_version();
        $orphans         = $this->subject->get_generated_attachment_ids();

        $this->trash_live_generation( $version_id );

        try {
            $this->create_content( $version_id );
            $this->restore_options( $version_id );
            $this->restore_theme_mods( $version_id );

            $problem = $this->verify( $version_id );

            if ( $problem !== null ) {
                throw new RuntimeException( $problem );
            }
        } catch ( Throwable $e ) {
            $this->rollback( $previous_active );

            Helper::log( 'Version restore failed: ' . $e->getMessage() );

            return $this->failure( $e->getMessage() );
        }

        $this->commit( $version_id, $orphans );

        return array(
            'ok'       => true,
            'code'     => '',
            'message'  => '',
            'warnings' => $this->build_slug_warnings(),
        );
    }

    private function trash_live_generation( int $version_id ): void {
        $this->trashed = array();

        foreach ( $this->subject->get_trashable_post_ids() as $post_id ) {
            if ( wp_trash_post( $post_id ) ) {
                $this->trashed[] = $post_id;
            }
        }

        ( new AffiliateBuilder() )->clear_catalog();

        update_option(
            VersionConstant::RESTORE_IN_PROGRESS_OPTION,
            array(
                'version_id' => $version_id,
                'trashed'    => $this->trashed,
                'started_at' => current_time( 'mysql' ),
            ),
            false
        );
    }

    private function create_content( int $version_id ): void {
        $snapshot_posts = $this->repository->get_posts( $version_id );
        $snapshot_terms = $this->repository->get_terms( $version_id );
        $relationships  = $this->repository->get_term_relationships( $version_id );
        $meta_by_post   = $this->group_meta( $this->repository->get_postmeta( $version_id ) );

        foreach ( $snapshot_terms as $term ) {
            $this->resolver->resolve_term( $term );
        }

        $areas_by_post = $this->collect_template_areas( $snapshot_terms, $relationships );

        foreach ( $snapshot_posts as $snapshot_post ) {
            $this->resolver->resolve( $snapshot_post, $areas_by_post[ (int) $snapshot_post->post_id ] ?? array() );
        }

        foreach ( $snapshot_posts as $snapshot_post ) {
            $live_id = $this->map->get( IdMap::KIND_POST, (int) $snapshot_post->post_id );
            if ( $live_id <= 0 ) {
                continue;
            }

            $this->write_content( $snapshot_post, $live_id );
            $this->write_meta( $live_id, $meta_by_post[ (int) $snapshot_post->post_id ] ?? array() );
        }

        $this->write_terms( $relationships );
    }

    private function write_content( object $snapshot_post, int $live_id ): void {
        $content = $this->rewriter->rewrite_content( (string) $snapshot_post->post_content );
        $parent  = $this->rewriter->rewrite_post_column( 'post_parent', (int) $snapshot_post->post_parent );

        wp_update_post(
            wp_slash(
                array(
                    'ID'                    => $live_id,
                    'post_content'          => $content,
                    'post_content_filtered' => (string) $snapshot_post->post_content_filtered,
                    'post_parent'           => $parent,
                    'post_status'           => (string) $snapshot_post->post_status,
                )
            )
        );
    }

    private function write_meta( int $live_id, array $meta_rows ): void {
        foreach ( $meta_rows as $row ) {
            $meta_key = (string) $row->meta_key;

            if ( $meta_key === '' ) {
                continue;
            }

            if ( $meta_key === '_elementor_data' ) {
                Helper::save_elementor_data( $live_id, $this->rewriter->rewrite_slugs( (string) $row->meta_value ) );
                continue;
            }

            $value = $this->rewriter->rewrite_postmeta( $meta_key, maybe_unserialize( $row->meta_value ) );

            update_post_meta( $live_id, $meta_key, wp_slash( $value ) );
        }
    }

    private function write_terms( array $relationships ): void {
        $by_object = array();

        foreach ( $relationships as $relationship ) {
            $live_object = $this->map->get( IdMap::KIND_POST, (int) $relationship->object_id );
            $live_term   = $this->map->get( IdMap::KIND_TERM, (int) $relationship->term_id );

            if ( $live_object <= 0 || $live_term <= 0 ) {
                continue;
            }

            $by_object[ $live_object ][ (string) $relationship->taxonomy ][] = $live_term;
        }

        foreach ( $by_object as $object_id => $taxonomies ) {
            foreach ( $taxonomies as $taxonomy => $term_ids ) {
                if ( ! taxonomy_exists( $taxonomy ) ) {
                    continue;
                }

                wp_set_object_terms( (int) $object_id, array_values( array_unique( $term_ids ) ), $taxonomy );
            }
        }
    }

    private function restore_options( int $version_id ): void {
        $snapshot_options = $this->repository->get_options( $version_id );

        foreach ( $snapshot_options as $option_name => $raw_value ) {
            if ( $this->subject->is_option_denied( $option_name ) ) {
                continue;
            }

            $value = $this->rewriter->rewrite_option_value( $option_name, maybe_unserialize( $raw_value ) );

            try {
                update_option( $option_name, $value );
            } catch ( Throwable $e ) {
                Helper::log( sprintf( 'Version restore could not write option %s: %s', $option_name, $e->getMessage() ) );
            }
        }

        // A version that had no hostinger_ai_woo must genuinely unset it.
        foreach ( $this->subject->get_option_names() as $live_option ) {
            if ( isset( $snapshot_options[ $live_option ] ) || ! $this->subject->is_option_removable( $live_option ) ) {
                continue;
            }

            delete_option( $live_option );
        }
    }

    private function restore_theme_mods( int $version_id ): void {
        $snapshot_mods = $this->repository->get_theme_mods( $version_id );

        foreach ( array_keys( $this->subject->get_theme_mods() ) as $live_key ) {
            if ( ! isset( $snapshot_mods[ $live_key ] ) ) {
                remove_theme_mod( $live_key );
            }
        }

        foreach ( $snapshot_mods as $mod_key => $raw_value ) {
            $result = $this->rewriter->rewrite_theme_mod( $mod_key, maybe_unserialize( $raw_value ) );

            if ( $result['dropped'] ) {
                remove_theme_mod( $mod_key );
                continue;
            }

            set_theme_mod( $mod_key, $result['value'] );
        }
    }

    private function verify( int $version_id ): ?string {
        foreach ( $this->repository->get_posts( $version_id ) as $snapshot_post ) {
            if ( $snapshot_post->post_type !== 'page' ) {
                continue;
            }

            if ( $this->map->get( IdMap::KIND_POST, (int) $snapshot_post->post_id ) <= 0 ) {
                return 'Restored page missing for snapshot post ' . $snapshot_post->post_id;
            }
        }

        $front_page_id = (int) get_option( 'page_on_front', 0 );

        if ( $front_page_id > 0 && 'publish' !== get_post_status( $front_page_id ) ) {
            return 'page_on_front does not point at a published page';
        }

        $navigation_problem = $this->verify_navigation_ref();

        if ( $navigation_problem !== null ) {
            return $navigation_problem;
        }

        $created_pages = get_option( 'hostinger_ai_created_pages', array() );
        if ( is_array( $created_pages ) ) {
            foreach ( $created_pages as $page ) {
                $page_id = is_array( $page ) ? (int) ( $page['page_id'] ?? 0 ) : 0;

                if ( $page_id > 0 && ! get_post_status( $page_id ) ) {
                    return 'hostinger_ai_created_pages references a missing post ' . $page_id;
                }
            }
        }

        return null;
    }

    private function verify_navigation_ref(): ?string {
        $header = get_posts(
            array(
                'post_type'      => 'wp_template_part',
                'post_status'    => 'any',
                'name'           => 'header',
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            )
        );

        if ( empty( $header ) ) {
            return null;
        }

        $ref = $this->find_navigation_ref( parse_blocks( $header[0]->post_content ) );

        if ( $ref <= 0 ) {
            return null;
        }

        if ( 'wp_navigation' !== get_post_type( $ref ) ) {
            return 'Header navigation ref points at a missing menu';
        }

        return null;
    }

    private function find_navigation_ref( array $blocks ): int {
        foreach ( $blocks as $block ) {
            if ( 'core/navigation' === ( $block['blockName'] ?? '' ) && ! empty( $block['attrs']['ref'] ) ) {
                return (int) $block['attrs']['ref'];
            }

            if ( ! empty( $block['innerBlocks'] ) ) {
                $ref = $this->find_navigation_ref( $block['innerBlocks'] );

                if ( $ref > 0 ) {
                    return $ref;
                }
            }
        }

        return 0;
    }

    private function commit( int $version_id, array $orphan_attachments ): void {
        foreach ( $this->trashed as $post_id ) {
            $this->delete_post( $post_id );
        }

        foreach ( $orphan_attachments as $attachment_id ) {
            if ( in_array( $attachment_id, $this->resolver->get_created_ids(), true ) ) {
                continue;
            }

            wp_delete_attachment( $attachment_id, true );
        }

        $this->repository->set_active( $version_id );

        delete_option( VersionConstant::RESTORE_IN_PROGRESS_OPTION );
    }

    private function rollback( ?object $previous_active ): void {
        foreach ( $this->resolver->get_created_ids() as $post_id ) {
            $this->delete_post( $post_id );
        }

        foreach ( $this->trashed as $post_id ) {
            wp_untrash_post( $post_id );
            wp_update_post(
                array(
                    'ID'          => $post_id,
                    'post_status' => 'publish',
                )
            );
        }

        if ( $previous_active !== null ) {
            $this->repository->set_active( (int) $previous_active->id );
        }

        delete_option( VersionConstant::RESTORE_IN_PROGRESS_OPTION );
    }

    // Elementor blocks deletion of the active kit with wp_die, so unset the option and set its bypass flag.
    private function delete_post( int $post_id ): void {
        if ( $post_id < 1 ) {
            return;
        }

        if ( (int) get_option( 'elementor_active_kit' ) === $post_id ) {
            delete_option( 'elementor_active_kit' );
        }

        $previous_force_delete    = $_GET['force_delete_kit'] ?? null;
        $_GET['force_delete_kit'] = '1';

        wp_delete_post( $post_id, true );

        if ( $previous_force_delete === null ) {
            unset( $_GET['force_delete_kit'] );
        } else {
            $_GET['force_delete_kit'] = $previous_force_delete;
        }
    }

    private function group_meta( array $meta_rows ): array {
        $grouped = array();

        foreach ( $meta_rows as $row ) {
            $grouped[ (int) $row->post_id ][] = $row;
        }

        return $grouped;
    }

    private function collect_template_areas( array $snapshot_terms, array $relationships ): array {
        $slugs_by_term = array();

        foreach ( $snapshot_terms as $term ) {
            if ( $term->taxonomy === 'wp_template_part_area' ) {
                $slugs_by_term[ (int) $term->term_id ] = (string) $term->slug;
            }
        }

        $areas = array();

        foreach ( $relationships as $relationship ) {
            $slug = $slugs_by_term[ (int) $relationship->term_id ] ?? null;

            if ( $slug === null ) {
                continue;
            }

            $areas[ (int) $relationship->object_id ][] = $slug;
        }

        return $areas;
    }

    private function build_slug_warnings(): array {
        $warnings = array();

        foreach ( $this->map->get_changed_slugs() as $old_slug => $new_slug ) {
            $warnings[] = array(
                'code' => 'slug_changed',
                'from' => $old_slug,
                'to'   => $new_slug,
            );
        }

        return $warnings;
    }

    private function failure( string $message ): array {
        return array(
            'ok'       => false,
            'code'     => self::CODE_RESTORE_FAILED,
            'message'  => $message,
            'warnings' => array(),
        );
    }
}
