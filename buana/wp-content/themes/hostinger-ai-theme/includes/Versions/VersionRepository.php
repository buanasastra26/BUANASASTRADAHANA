<?php

namespace Hostinger\AiTheme\Versions;

defined( 'ABSPATH' ) || exit;

class VersionRepository {
    public function insert_version( array $data ): int {
        global $wpdb;

        $defaults = array(
            'sequence'       => $this->next_sequence(),
            'label'          => '',
            'brand_name'     => '',
            'description'    => '',
            'builder_type'   => '',
            'website_type'   => '',
            'locale'         => '',
            'front_page_id'  => 0,
            'is_active'      => 0,
            'schema_version' => VersionConstant::SCHEMA_VERSION,
            'theme_version'  => '',
            'page_count'     => 0,
            'post_count'     => 0,
            'product_count'  => 0,
            'size_bytes'     => 0,
            'created_at'     => current_time( 'mysql' ),
        );

        $row = array_merge( $defaults, array_intersect_key( $data, $defaults ) );

        $wpdb->insert( Schema::table( VersionConstant::TABLE_VERSIONS ), $row );

        return (int) $wpdb->insert_id;
    }

    public function next_sequence(): int {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_VERSIONS );

        $max = $wpdb->get_var( "SELECT MAX(sequence) FROM {$table}" );

        return (int) $max + 1;
    }

    public function insert_rows( string $table_suffix, array $rows ): int {
        global $wpdb;

        if ( empty( $rows ) ) {
            return 0;
        }

        $table    = Schema::table( $table_suffix );
        $columns  = array_keys( reset( $rows ) );
        $col_list = '`' . implode( '`, `', $columns ) . '`';
        $inserted = 0;

        foreach ( array_chunk( $rows, VersionConstant::INSERT_BATCH_SIZE ) as $chunk ) {
            $values       = array();
            $placeholders = array();

            foreach ( $chunk as $row ) {
                $row_placeholders = array();

                foreach ( $columns as $column ) {
                    $value = $row[ $column ] ?? null;

                    if ( $value === null ) {
                        $row_placeholders[] = 'NULL';
                        continue;
                    }

                    $row_placeholders[] = is_int( $value ) ? '%d' : '%s';
                    $values[]           = $value;
                }

                $placeholders[] = '(' . implode( ', ', $row_placeholders ) . ')';
            }

            $sql = "INSERT INTO {$table} ({$col_list}) VALUES " . implode( ', ', $placeholders );

            $result = $wpdb->query( $wpdb->prepare( $sql, $values ) );

            if ( $result !== false ) {
                $inserted += (int) $result;
            }
        }

        return $inserted;
    }

    public function list_versions(): array {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_VERSIONS );

        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC" );
    }

    public function get_version( int $version_id ): ?object {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_VERSIONS );

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $version_id ) );

        return $row === null ? null : $row;
    }

    public function get_active_version(): ?object {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_VERSIONS );

        $row = $wpdb->get_row( "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY id DESC LIMIT 1" );

        return $row === null ? null : $row;
    }

    public function count_versions(): int {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_VERSIONS );

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    public function update_version( int $version_id, array $data ): void {
        global $wpdb;

        if ( empty( $data ) ) {
            return;
        }

        $wpdb->update(
            Schema::table( VersionConstant::TABLE_VERSIONS ),
            $data,
            array( 'id' => $version_id )
        );
    }

    public function set_active( int $version_id ): void {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_VERSIONS );

        $wpdb->query( "UPDATE {$table} SET is_active = 0 WHERE is_active = 1" );
        $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET is_active = 1 WHERE id = %d", $version_id ) );

        $this->clear_pending_screenshots( $version_id );
    }

    public function get_posts( int $version_id ): array {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_POSTS );

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE version_id = %d ORDER BY id ASC", $version_id ) );
    }

    public function get_postmeta( int $version_id ): array {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_POSTMETA );

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE version_id = %d ORDER BY id ASC", $version_id ) );
    }

    public function get_options( int $version_id ): array {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_OPTIONS );

        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$table} WHERE version_id = %d", $version_id ) );

        $options = array();

        foreach ( $rows as $row ) {
            $options[ $row->option_name ] = $row->option_value;
        }

        return $options;
    }

    public function get_terms( int $version_id ): array {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_TERMS );

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE version_id = %d ORDER BY parent ASC, id ASC", $version_id ) );
    }

    public function get_term_relationships( int $version_id ): array {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_TERM_RELATIONSHIPS );

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE version_id = %d ORDER BY id ASC", $version_id ) );
    }

    public function get_theme_mods( int $version_id ): array {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_THEME_MODS );

        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT mod_key, mod_value FROM {$table} WHERE version_id = %d", $version_id ) );

        $mods = array();

        foreach ( $rows as $row ) {
            $mods[ $row->mod_key ] = $row->mod_value;
        }

        return $mods;
    }

    public function get_plugins( int $version_id ): array {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_PLUGINS );

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE version_id = %d", $version_id ) );
    }

    public function calculate_size_bytes( int $version_id ): int {
        global $wpdb;

        $posts    = Schema::table( VersionConstant::TABLE_POSTS );
        $postmeta = Schema::table( VersionConstant::TABLE_POSTMETA );
        $options  = Schema::table( VersionConstant::TABLE_OPTIONS );

        $size  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(LENGTH(post_content) + LENGTH(post_title) + LENGTH(post_excerpt) + LENGTH(post_content_filtered)), 0) FROM {$posts} WHERE version_id = %d", $version_id ) );
        $size += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(LENGTH(COALESCE(meta_key, '')) + LENGTH(COALESCE(meta_value, ''))), 0) FROM {$postmeta} WHERE version_id = %d", $version_id ) );
        $size += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(LENGTH(option_name) + LENGTH(option_value)), 0) FROM {$options} WHERE version_id = %d", $version_id ) );

        return $size;
    }

    public function count_posts_by_type( int $version_id ): array {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_POSTS );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_type, COUNT(*) AS total FROM {$table} WHERE version_id = %d AND role = %s GROUP BY post_type",
                $version_id,
                VersionConstant::ROLE_GENERATED
            )
        );

        $counts = array();

        foreach ( $rows as $row ) {
            $counts[ $row->post_type ] = (int) $row->total;
        }

        return $counts;
    }

    public function delete( int $version_id ): bool {
        global $wpdb;

        foreach ( VersionConstant::CHILD_TABLES as $suffix ) {
            $table = Schema::table( $suffix );

            $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE version_id = %d", $version_id ) );
        }

        $versions = Schema::table( VersionConstant::TABLE_VERSIONS );

        return (bool) $wpdb->query( $wpdb->prepare( "DELETE FROM {$versions} WHERE id = %d", $version_id ) );
    }

    public function update_screenshot( int $version_id, array $data ): void {
        $allowed = array(
            'screenshot_status',
            'screenshot_file',
            'screenshot_hash',
            'screenshot_requested_at',
            'screenshot_attempts',
        );

        $row = array_intersect_key( $data, array_flip( $allowed ) );

        if ( empty( $row ) ) {
            return;
        }

        $this->update_version( $version_id, $row );
    }

    public function find_version_by_screenshot_hash( string $hash, int $exclude_version_id = 0 ): int {
        global $wpdb;

        if ( $hash === '' ) {
            return 0;
        }

        $table = Schema::table( VersionConstant::TABLE_VERSIONS );

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE screenshot_hash = %s AND id != %d LIMIT 1",
                $hash,
                $exclude_version_id
            )
        );
    }

    public function clear_pending_screenshots( int $except_version_id ): void {
        global $wpdb;

        $table = Schema::table( VersionConstant::TABLE_VERSIONS );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET screenshot_status = %s, screenshot_requested_at = NULL, screenshot_attempts = 0
                 WHERE id != %d AND screenshot_status IN ( %s, %s )",
                VersionConstant::SCREENSHOT_FAILED,
                $except_version_id,
                VersionConstant::SCREENSHOT_QUEUED,
                VersionConstant::SCREENSHOT_PENDING
            )
        );
    }

    public function get_prunable_versions( int $max_versions = VersionConstant::MAX_VERSIONS ): array {
        global $wpdb;

        $overflow = $this->count_versions() - $max_versions;

        if ( $overflow <= 0 ) {
            return array();
        }

        $table = Schema::table( VersionConstant::TABLE_VERSIONS );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE is_active = 0 ORDER BY created_at ASC, id ASC LIMIT %d",
                $overflow
            )
        );
    }
}
