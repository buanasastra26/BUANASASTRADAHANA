<?php

namespace Hostinger\AiTheme\Versions\Restore;

use Hostinger\AiTheme\Builder\AbstractPluginBuilder;
use Hostinger\AiTheme\Builder\AffiliateBuilder;
use Hostinger\AiTheme\Builder\ElementorBuilder;
use Hostinger\AiTheme\Builder\GenerationState;
use Hostinger\AiTheme\Builder\HostingerReachBuilder;
use Hostinger\AiTheme\Builder\WooBuilder;
use Hostinger\AiTheme\Builder\ImageManager;
use Hostinger\AiTheme\Versions\VersionConstant;
use Hostinger\AiTheme\Versions\VersionRepository;
use Hostinger\AiTheme\Versions\VersionSubject;

defined( 'ABSPATH' ) || exit;

class Preflight {
    public const CODE_SCHEMA_UNSUPPORTED = 'restore_schema_unsupported';
    public const CODE_PREFLIGHT_FAILED   = 'restore_preflight_failed';
    public const CODE_NOT_FOUND          = 'restore_version_not_found';

    private VersionRepository $repository;
    private VersionSubject $subject;

    public function __construct( ?VersionRepository $repository = null, ?VersionSubject $subject = null ) {
        $this->repository = $repository ?? new VersionRepository();
        $this->subject    = $subject ?? new VersionSubject();
    }

    public function check( int $version_id ): array {
        $version = $this->repository->get_version( $version_id );
        if ( $version === null ) {
            return $this->fail( self::CODE_NOT_FOUND, __( 'Version not found.', 'hostinger-ai-theme' ) );
        }

        if ( (int) $version->schema_version !== VersionConstant::SCHEMA_VERSION ) {
            return $this->fail(
                self::CODE_SCHEMA_UNSUPPORTED,
                __( 'This version was saved in an older format and can no longer be restored.', 'hostinger-ai-theme' )
            );
        }

        if ( GenerationState::is_in_progress() ) {
            return $this->fail(
                self::CODE_PREFLIGHT_FAILED,
                __( 'A website generation is currently running.', 'hostinger-ai-theme' )
            );
        }

        $snapshot_posts = $this->repository->get_posts( $version_id );
        if ( ! $this->has_page( $snapshot_posts ) ) {
            return $this->fail(
                self::CODE_PREFLIGHT_FAILED,
                __( 'This version contains no pages.', 'hostinger-ai-theme' )
            );
        }

        $plugin_error = $this->ensure_plugins( $version );
        if ( $plugin_error !== null ) {
            return $this->fail( self::CODE_PREFLIGHT_FAILED, $plugin_error );
        }

        $warnings = array_merge(
            $this->collect_slug_warnings( $snapshot_posts ),
            $this->collect_logo_warnings( $version_id )
        );

        return array(
            'ok'       => true,
            'code'     => '',
            'message'  => '',
            'warnings' => $warnings,
        );
    }

    private function has_page( array $snapshot_posts ): bool {
        foreach ( $snapshot_posts as $post ) {
            if ( $post->post_type === 'page' ) {
                return true;
            }
        }

        return false;
    }

    private function ensure_plugins( object $version ): ?string {
        foreach ( $this->get_snapshot_active_plugins( (int) $version->id ) as $plugin_file ) {
            $builder = $this->get_builder( $plugin_file );

            if ( $builder === null ) {
                continue;
            }

            $result = $builder->force_boot();

            if ( is_wp_error( $result ) ) {
                return $result->get_error_message();
            }
        }

        return null;
    }

    private function get_snapshot_active_plugins( int $version_id ): array {
        $known  = array_values( VersionSubject::KNOWN_PLUGINS );
        $active = array();

        foreach ( $this->repository->get_plugins( $version_id ) as $plugin ) {
            $plugin_file = (string) $plugin->plugin_file;

            if ( (int) $plugin->is_active === 1 && in_array( $plugin_file, $known, true ) ) {
                $active[] = $plugin_file;
            }
        }

        return $active;
    }

    private function get_builder( string $plugin_file ): ?AbstractPluginBuilder {
        switch ( $plugin_file ) {
            case VersionSubject::KNOWN_PLUGINS['elementor']:
                return new ElementorBuilder();

            case VersionSubject::KNOWN_PLUGINS['woocommerce']:
                return new WooBuilder( new ImageManager() );

            case VersionSubject::KNOWN_PLUGINS['affiliate']:
                return new AffiliateBuilder();

            case VersionSubject::KNOWN_PLUGINS['reach']:
                return new HostingerReachBuilder();
        }

        return null;
    }

    private function collect_slug_warnings( array $snapshot_posts ): array {
        global $wpdb;

        $generated = $this->subject->get_trashable_post_ids();
        $warnings  = array();

        foreach ( $snapshot_posts as $post ) {
            $slug = (string) $post->post_name;

            if ( $slug === '' || $post->post_status === 'trash' || ! in_array( $post->post_type, VersionSubject::TRASHABLE_POST_TYPES, true ) ) {
                continue;
            }

            $holders = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND post_status != 'trash'",
                    $slug,
                    $post->post_type
                )
            );

            $user_held = array_diff( array_map( 'intval', $holders ), $generated );

            if ( empty( $user_held ) ) {
                continue;
            }

            $warnings[] = array(
                'code' => 'slug_collision',
                'slug' => $slug,
            );
        }

        return $warnings;
    }

    private function collect_logo_warnings( int $version_id ): array {
        $mods = $this->repository->get_theme_mods( $version_id );

        if ( ! isset( $mods['custom_logo'] ) ) {
            return array();
        }

        $logo_id = (int) maybe_unserialize( $mods['custom_logo'] );

        if ( $logo_id <= 0 || 'attachment' === get_post_type( $logo_id ) ) {
            return array();
        }

        return array(
            array(
                'code'          => 'missing_logo_attachment',
                'attachment_id' => $logo_id,
            ),
        );
    }

    private function fail( string $code, string $message ): array {
        return array(
            'ok'       => false,
            'code'     => $code,
            'message'  => $message,
            'warnings' => array(),
        );
    }
}
