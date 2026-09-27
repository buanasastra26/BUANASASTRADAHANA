<?php

namespace Hostinger\AiTheme\Rest;

use Hostinger\AiTheme\Versions\Restore\Preflight;
use Hostinger\AiTheme\Versions\Restore\SnapshotRestorer;
use Hostinger\AiTheme\Versions\Screenshot\ScreenshotService;
use Hostinger\AiTheme\Versions\VersionConstant;
use Hostinger\AiTheme\Versions\VersionManager;
use Hostinger\AiTheme\Versions\VersionRepository;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class VersionRoutes {
    private VersionManager $manager;
    private VersionRepository $repository;
    private ScreenshotService $screenshots;

    public function __construct( ?VersionManager $manager = null ) {
        $this->manager     = $manager ?? new VersionManager();
        $this->repository  = $this->manager->get_repository();
        $this->screenshots = $this->manager->get_screenshots();
    }

    public function get_versions( WP_REST_Request $request ): WP_REST_Response {
        $versions = array_map(
            fn( object $version ): array => $this->present( $version ),
            $this->repository->list_versions()
        );

        return $this->respond(
            array(
                'versions'     => $versions,
                'max_versions' => VersionConstant::MAX_VERSIONS,
            )
        );
    }

    public function get_version( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $version = $this->repository->get_version( (int) $request->get_param( 'id' ) );

        if ( $version === null ) {
            return $this->not_found();
        }

        return $this->respond( array( 'version' => $this->present( $version ) ) );
    }

    public function restore_version( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $version_id = (int) $request->get_param( 'id' );
        $version    = $this->repository->get_version( $version_id );

        if ( $version === null ) {
            return $this->not_found();
        }

        $result = $this->manager->restore( $version_id );

        if ( ! $result['ok'] ) {
            return new WP_Error(
                $result['code'],
                $result['message'],
                array( 'status' => $this->status_for( $result['code'] ) )
            );
        }

        $restored = $this->repository->get_version( $version_id );

        return $this->respond(
            array(
                'version'  => $this->present( $restored ?? $version ),
                'warnings' => $result['warnings'],
            )
        );
    }

    public function delete_version( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $version_id = (int) $request->get_param( 'id' );
        $version    = $this->repository->get_version( $version_id );

        if ( $version === null ) {
            return $this->not_found();
        }

        if ( (int) $version->is_active === 1 ) {
            return new WP_Error(
                'version_active',
                __( 'The active version cannot be deleted.', 'hostinger-ai-theme' ),
                array( 'status' => WP_Http::CONFLICT )
            );
        }

        return $this->respond( array( 'deleted' => $this->manager->delete( $version_id ) ) );
    }

    public function request_screenshot( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $version_id = (int) $request->get_param( 'id' );

        if ( $this->repository->get_version( $version_id ) === null ) {
            return $this->not_found();
        }

        return $this->respond( array( 'screenshot' => $this->screenshots->request( $version_id ) ) );
    }

    public function get_screenshot( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $version_id = (int) $request->get_param( 'id' );

        if ( $this->repository->get_version( $version_id ) === null ) {
            return $this->not_found();
        }

        return $this->respond( array( 'screenshot' => $this->screenshots->sync( $version_id ) ) );
    }

    private function present( object $version ): array {
        $screenshot = $this->screenshots->state( $version );

        return array(
            'id'                => (int) $version->id,
            'sequence'          => (int) $version->sequence,
            'name'              => $this->display_name( $version ),
            'label'             => (string) $version->label,
            'created_at'        => (string) $version->created_at,
            'date'              => (string) $version->created_at,
            'builder_type'      => (string) $version->builder_type,
            'website_type'      => (string) $version->website_type,
            'brand_name'        => (string) $version->brand_name,
            'description'       => (string) $version->description,
            'page_count'        => (int) $version->page_count,
            'post_count'        => (int) $version->post_count,
            'product_count'     => (int) $version->product_count,
            'is_current'        => (int) $version->is_active === 1,
            'restorable'        => (int) $version->schema_version === VersionConstant::SCHEMA_VERSION,
            'size_bytes'        => (int) $version->size_bytes,
            'screenshot_url'    => $screenshot['url'],
            'screenshot_status' => $screenshot['status'],
        );
    }

    private function display_name( object $version ): string {
        $label = trim( (string) $version->label );

        if ( $label === '' ) {
            $label = trim( (string) $version->brand_name );
        }

        if ( $label !== '' ) {
            return $label;
        }

        return sprintf(
            /* translators: %d: version number */
            __( 'Version %d', 'hostinger-ai-theme' ),
            (int) $version->sequence
        );
    }

    private function status_for( string $code ): int {
        if ( $code === SnapshotRestorer::CODE_RESTORE_FAILED ) {
            return WP_Http::INTERNAL_SERVER_ERROR;
        }

        if ( $code === Preflight::CODE_NOT_FOUND ) {
            return WP_Http::NOT_FOUND;
        }

        return WP_Http::CONFLICT;
    }

    private function not_found(): WP_Error {
        return new WP_Error(
            'version_not_found',
            __( 'Version not found.', 'hostinger-ai-theme' ),
            array( 'status' => WP_Http::NOT_FOUND )
        );
    }

    private function respond( array $data ): WP_REST_Response {
        $response = new WP_REST_Response( array( 'data' => $data ) );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }
}
