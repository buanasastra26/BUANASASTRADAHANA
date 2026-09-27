<?php

namespace Hostinger\AiTheme\Versions\Capture;

use Hostinger\AiTheme\Versions\VersionConstant;
use Hostinger\AiTheme\Versions\VersionRepository;
use Hostinger\AiTheme\Versions\VersionSubject;

defined( 'ABSPATH' ) || exit;

class PostCapturer {
    private const POST_COLUMNS = array(
        'post_author',
        'post_date',
        'post_date_gmt',
        'post_content',
        'post_title',
        'post_excerpt',
        'post_status',
        'comment_status',
        'ping_status',
        'post_password',
        'post_name',
        'to_ping',
        'pinged',
        'post_modified',
        'post_modified_gmt',
        'post_content_filtered',
        'post_parent',
        'guid',
        'menu_order',
        'post_type',
        'post_mime_type',
        'comment_count',
    );

    private VersionSubject $subject;
    private VersionRepository $repository;

    public function __construct( VersionSubject $subject, VersionRepository $repository ) {
        $this->subject    = $subject;
        $this->repository = $repository;
    }

    public function capture( int $version_id ): array {
        global $wpdb;

        $roles = $this->subject->get_post_roles();
        if ( empty( $roles ) ) {
            return array();
        }

        $post_ids     = array_keys( $roles );
        $placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
        $columns      = implode( ', ', self::POST_COLUMNS );

        $posts = $wpdb->get_results(
            $wpdb->prepare( "SELECT ID, {$columns} FROM {$wpdb->posts} WHERE ID IN ({$placeholders})", $post_ids ),
            ARRAY_A
        );

        $rows = array();
        foreach ( $posts as $post ) {
            $post_id = (int) $post['ID'];
            unset( $post['ID'] );

            $row = array(
                'version_id' => $version_id,
                'post_id'    => $post_id,
                'role'       => $roles[ $post_id ] ?? VersionConstant::ROLE_GENERATED,
            );

            foreach ( self::POST_COLUMNS as $column ) {
                $row[ $column ] = $post[ $column ] ?? '';
            }

            $rows[] = $row;
        }

        $this->repository->insert_rows( VersionConstant::TABLE_POSTS, $rows );

        $this->capture_meta( $version_id, $post_ids );

        return $post_ids;
    }

    private function capture_meta( int $version_id, array $post_ids ): void {
        global $wpdb;

        $rows = array();

        foreach ( array_chunk( $post_ids, VersionConstant::INSERT_BATCH_SIZE ) as $chunk ) {
            $placeholders = implode( ', ', array_fill( 0, count( $chunk ), '%d' ) );

            $meta = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$placeholders})",
                    $chunk
                ),
                ARRAY_A
            );

            foreach ( $meta as $entry ) {
                $rows[] = array(
                    'version_id' => $version_id,
                    'post_id'    => (int) $entry['post_id'],
                    'meta_key'   => $entry['meta_key'],
                    'meta_value' => $entry['meta_value'],
                );
            }
        }

        $this->repository->insert_rows( VersionConstant::TABLE_POSTMETA, $rows );
    }
}
