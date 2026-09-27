<?php

namespace Hostinger\AiTheme\Versions\Restore;

defined( 'ABSPATH' ) || exit;

class IdMap {
    public const KIND_POST       = 'post';
    public const KIND_ATTACHMENT = 'attachment';
    public const KIND_TERM       = 'term';
    private array $maps = array(
        self::KIND_POST       => array(),
        self::KIND_ATTACHMENT => array(),
        self::KIND_TERM       => array(),
    );
    private array $slugs = array();

    public function add( string $kind, int $snapshot_id, int $live_id ): void {
        if ( ! isset( $this->maps[ $kind ] ) ) {
            $this->maps[ $kind ] = array();
        }

        $this->maps[ $kind ][ $snapshot_id ] = $live_id;
    }

    public function add_post( int $snapshot_id, int $live_id ): void {
        $this->add( self::KIND_POST, $snapshot_id, $live_id );
    }

    public function add_attachment( int $snapshot_id, int $live_id ): void {
        $this->add( self::KIND_ATTACHMENT, $snapshot_id, $live_id );
        $this->add( self::KIND_POST, $snapshot_id, $live_id );
    }

    public function add_term( int $snapshot_id, int $live_id ): void {
        $this->add( self::KIND_TERM, $snapshot_id, $live_id );
    }

    public function has( string $kind, int $snapshot_id ): bool {
        return isset( $this->maps[ $kind ][ $snapshot_id ] );
    }

    public function get( string $kind, int $snapshot_id, int $default = 0 ): int {
        return $this->maps[ $kind ][ $snapshot_id ] ?? $default;
    }

    public function all( string $kind ): array {
        return $this->maps[ $kind ] ?? array();
    }

    public function all_snapshot_ids(): array {
        $ids = array();

        foreach ( $this->maps as $map ) {
            $ids = array_merge( $ids, array_keys( $map ) );
        }

        return array_values( array_unique( $ids ) );
    }

    public function add_slug( string $snapshot_slug, string $live_slug ): void {
        if ( $snapshot_slug === '' ) {
            return;
        }

        $this->slugs[ $snapshot_slug ] = $live_slug;
    }

    public function get_changed_slugs(): array {
        return array_filter(
            $this->slugs,
            static fn( string $live, string $snapshot ): bool => $live !== $snapshot,
            ARRAY_FILTER_USE_BOTH
        );
    }

    public function get_slugs(): array {
        return $this->slugs;
    }
}
