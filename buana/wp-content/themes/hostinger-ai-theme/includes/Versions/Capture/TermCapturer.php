<?php

namespace Hostinger\AiTheme\Versions\Capture;

use Hostinger\AiTheme\Versions\VersionConstant;
use Hostinger\AiTheme\Versions\VersionRepository;
use Hostinger\AiTheme\Versions\VersionSubject;

defined( 'ABSPATH' ) || exit;

class TermCapturer {
    private VersionSubject $subject;
    private VersionRepository $repository;

    public function __construct( VersionSubject $subject, VersionRepository $repository ) {
        $this->subject    = $subject;
        $this->repository = $repository;
    }

    public function capture( int $version_id, array $object_ids ): void {
        $relationships = $this->collect_relationships( $object_ids );

        $term_ids = array_map( static fn( array $row ): int => $row['term_id'], $relationships );
        $term_ids = array_merge( $term_ids, $this->get_tracked_product_category_ids() );
        $term_ids = array_values( array_unique( array_filter( $term_ids ) ) );

        $this->capture_terms( $version_id, $term_ids );

        $rows = array();

        foreach ( $relationships as $relationship ) {
            $rows[] = array(
                'version_id' => $version_id,
                'object_id'  => $relationship['object_id'],
                'term_id'    => $relationship['term_id'],
                'taxonomy'   => $relationship['taxonomy'],
                'term_order' => $relationship['term_order'],
            );
        }

        $this->repository->insert_rows( VersionConstant::TABLE_TERM_RELATIONSHIPS, $rows );
    }

    private function collect_relationships( array $object_ids ): array {
        global $wpdb;

        if ( empty( $object_ids ) ) {
            return array();
        }

        $taxonomies         = $this->subject->get_taxonomies();
        $tax_placeholders   = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );
        $relationships      = array();

        foreach ( array_chunk( $object_ids, VersionConstant::INSERT_BATCH_SIZE ) as $chunk ) {
            $object_placeholders = implode( ', ', array_fill( 0, count( $chunk ), '%d' ) );

            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT tr.object_id, tr.term_taxonomy_id, tr.term_order, tt.term_id, tt.taxonomy
                     FROM {$wpdb->term_relationships} tr
                     INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                     WHERE tr.object_id IN ({$object_placeholders})
                     AND tt.taxonomy IN ({$tax_placeholders})",
                    array_merge( $chunk, $taxonomies )
                ),
                ARRAY_A
            );

            foreach ( $rows as $row ) {
                $relationships[] = array(
                    'object_id'  => (int) $row['object_id'],
                    'term_id'    => (int) $row['term_id'],
                    'taxonomy'   => (string) $row['taxonomy'],
                    'term_order' => (int) $row['term_order'],
                );
            }
        }

        return $relationships;
    }

    private function get_tracked_product_category_ids(): array {
        $ids = get_option( VersionSubject::PRODUCT_CATEGORY_OPTION, array() );

        if ( ! is_array( $ids ) ) {
            return array();
        }

        return array_map( 'intval', $ids );
    }

    private function capture_terms( int $version_id, array $term_ids ): void {
        global $wpdb;

        if ( empty( $term_ids ) ) {
            return;
        }

        $taxonomies       = $this->subject->get_taxonomies();
        $tax_placeholders = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );
        $rows             = array();

        foreach ( array_chunk( $term_ids, VersionConstant::INSERT_BATCH_SIZE ) as $chunk ) {
            $term_placeholders = implode( ', ', array_fill( 0, count( $chunk ), '%d' ) );

            $terms = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT t.term_id, t.name, t.slug, t.term_group, tt.taxonomy, tt.description, tt.parent
                     FROM {$wpdb->terms} t
                     INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
                     WHERE t.term_id IN ({$term_placeholders})
                     AND tt.taxonomy IN ({$tax_placeholders})",
                    array_merge( $chunk, $taxonomies )
                ),
                ARRAY_A
            );

            foreach ( $terms as $term ) {
                $rows[] = array(
                    'version_id'  => $version_id,
                    'term_id'     => (int) $term['term_id'],
                    'name'        => (string) $term['name'],
                    'slug'        => (string) $term['slug'],
                    'term_group'  => (int) $term['term_group'],
                    'taxonomy'    => (string) $term['taxonomy'],
                    'description' => (string) $term['description'],
                    'parent'      => (int) $term['parent'],
                );
            }
        }

        $this->repository->insert_rows( VersionConstant::TABLE_TERMS, $rows );
    }
}
