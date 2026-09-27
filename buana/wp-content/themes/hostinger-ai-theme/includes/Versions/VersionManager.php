<?php

namespace Hostinger\AiTheme\Versions;

use Hostinger\AiTheme\Builder\Helper;
use Hostinger\AiTheme\Rest\Endpoints;
use Hostinger\AiTheme\Versions\Capture\SnapshotCapturer;
use Hostinger\AiTheme\Versions\Restore\Fixup;
use Hostinger\AiTheme\Versions\Restore\Preflight;
use Hostinger\AiTheme\Versions\Restore\SnapshotRestorer;
use Hostinger\AiTheme\Versions\Screenshot\ScreenshotService;
use Hostinger\Amplitude\AmplitudeManager;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils;
use Throwable;

defined( 'ABSPATH' ) || exit;

class VersionManager {
    private const AMPLITUDE_EVENT_RESTORED = 'wordpress.ai_builder.version_restored';

    private VersionRepository $repository;
    private VersionSubject $subject;
    private ScreenshotService $screenshots;

    public function __construct( ?VersionRepository $repository = null, ?VersionSubject $subject = null, ?ScreenshotService $screenshots = null ) {
        $this->repository  = $repository ?? new VersionRepository();
        $this->subject     = $subject ?? new VersionSubject();
        $this->screenshots = $screenshots ?? new ScreenshotService( $this->repository );
    }

    public function capture_after_generation(): int {
        try {
            Schema::maybe_install();

            $version_id = ( new SnapshotCapturer( $this->subject, $this->repository ) )->capture();
            if ( $version_id <= 0 ) {
                return 0;
            }

            $this->repository->set_active( $version_id );
            $this->screenshots->queue( $version_id );
            $this->prune();

            Helper::reset_website_versions_count_cache();

            return $version_id;
        } catch ( Throwable $e ) {
            Helper::log( 'Version capture failed: ' . $e->getMessage() );

            return 0;
        }
    }

    public function restore( int $version_id ): array {
        $preflight = ( new Preflight( $this->repository, $this->subject ) )->check( $version_id );
        if ( ! $preflight['ok'] ) {
            return $preflight;
        }

        $outgoing_builder_type = (string) get_option( 'hostinger_ai_builder_type', '' );
        $restorer = new SnapshotRestorer( $this->repository, $this->subject );
        $result   = $restorer->restore( $version_id );

        if ( ! $result['ok'] ) {
            return $result;
        }

        $version = $this->repository->get_version( $version_id );
        if ( $version !== null ) {
            try {
                ( new Fixup() )->run( $version, $outgoing_builder_type );
            } catch ( Throwable $e ) {
                Helper::log( 'Version restore fixup failed: ' . $e->getMessage() );
            }

            $this->send_restored_event( $version );
            $this->maybe_queue_screenshot( $version );
        }

        $result['warnings'] = array_merge( $preflight['warnings'], $result['warnings'] );

        return $result;
    }

    public function prune(): int {
        $pruned = 0;

        foreach ( $this->repository->get_prunable_versions() as $version ) {
            if ( $this->delete( (int) $version->id ) ) {
                $pruned++;
            }
        }

        return $pruned;
    }

    public function delete( int $version_id ): bool {
        $version = $this->repository->get_version( $version_id );

        if ( $version === null ) {
            return false;
        }

        $this->screenshots->delete( $version_id );

        $deleted = $this->repository->delete( $version_id );

        if ( $deleted ) {
            Helper::reset_website_versions_count_cache();
        }

        return $deleted;
    }

    private function maybe_queue_screenshot( object $version ): void {
        if ( $this->screenshots->state( $version )['status'] === VersionConstant::SCREENSHOT_READY ) {
            return;
        }

        $this->screenshots->queue( (int) $version->id );
    }

    private function send_restored_event( object $version ): void {
        try {
            $helper = new Utils();
            $config = new Config();

            $client = new Client(
                $config->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI ),
                array(
                    Config::TOKEN_HEADER  => $helper::getApiToken(),
                    Config::DOMAIN_HEADER => $helper->getHostInfo(),
                )
            );

            ( new AmplitudeManager( $helper, $config, $client ) )->sendRequest(
                Endpoints::AMPLITUDE_ENDPOINT,
                array(
                    'action'       => self::AMPLITUDE_EVENT_RESTORED,
                    'builder_type' => (string) $version->builder_type,
                    'website_type' => (string) $version->website_type,
                )
            );
        } catch ( Throwable $e ) {
            Helper::log( 'Version restore event failed: ' . $e->getMessage() );
        }
    }

    public function get_repository(): VersionRepository {
        return $this->repository;
    }

    public function get_subject(): VersionSubject {
        return $this->subject;
    }

    public function get_screenshots(): ScreenshotService {
        return $this->screenshots;
    }
}
