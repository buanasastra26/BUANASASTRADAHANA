<?php

namespace Hostinger\AiTheme\Versions\Screenshot;

use Hostinger\AiTheme\Builder\Helper;
use Hostinger\AiTheme\Builder\RequestClient;
use Hostinger\AiTheme\Builder\SoftwareIdTrait;
use Hostinger\AiTheme\Versions\VersionConstant;
use Hostinger\AiTheme\Versions\VersionRepository;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils;
use Throwable;

defined( 'ABSPATH' ) || exit;

class ScreenshotService {
    use SoftwareIdTrait;
    private const MAX_ATTEMPTS = 24;
    private const MAX_WAIT_SECONDS = 300;
    private VersionRepository $repository;
    private ScreenshotStore $store;
    private ?ScreenshotClient $client;
    private $wh_api_client;

    public function __construct(
        ?VersionRepository $repository = null,
        ?ScreenshotStore $store = null,
        ?ScreenshotClient $client = null,
        $wh_api_client = null
    ) {
        $this->repository    = $repository ?? new VersionRepository();
        $this->store         = $store ?? new ScreenshotStore();
        $this->client        = $client;
        $this->wh_api_client = $wh_api_client;
    }

    public function queue( int $version_id ): void {
        if ( $version_id <= 0 ) {
            return;
        }

        $this->repository->update_screenshot(
            $version_id,
            array(
                'screenshot_status'       => VersionConstant::SCREENSHOT_QUEUED,
                'screenshot_requested_at' => null,
                'screenshot_attempts'     => 0,
            )
        );
    }

    public function request( int $version_id ): array {
        $version = $this->repository->get_version( $version_id );
        if ( $version === null ) {
            return $this->state( null );
        }

        if ( (string) $version->screenshot_status === VersionConstant::SCREENSHOT_READY && $this->has_file( $version ) ) {
            return $this->state( $version );
        }

        if ( (int) $version->is_active !== 1 ) {
            return $this->fail( $version_id, 'Only the active version can be screenshotted.' );
        }

        $software_id = $this->software_id();
        if ( $software_id === '' ) {
            return $this->fail( $version_id, 'Software ID not available.' );
        }

        $response = $this->client()->request( $software_id );
        if ( ! $this->is_success( $response['code'] ) ) {
            return $this->fail( $version_id, sprintf( 'The screenshot request returned %d.', $response['code'] ) );
        }

        $this->repository->update_screenshot(
            $version_id,
            array(
                'screenshot_status'       => VersionConstant::SCREENSHOT_PENDING,
                'screenshot_requested_at' => current_time( 'mysql', true ),
                'screenshot_attempts'     => 0,
            )
        );

        return $this->state( $this->repository->get_version( $version_id ) );
    }

    public function sync( int $version_id ): array {
        $version = $this->repository->get_version( $version_id );
        if ( $version === null ) {
            return $this->state( null );
        }

        $status = (string) $version->screenshot_status;
        if ( $status === VersionConstant::SCREENSHOT_READY && $this->has_file( $version ) ) {
            return $this->state( $version );
        }

        if ( $status === VersionConstant::SCREENSHOT_FAILED ) {
            return $this->state( $version );
        }

        if ( $status !== VersionConstant::SCREENSHOT_PENDING ) {
            return $this->request( $version_id );
        }

        if ( $this->is_exhausted( $version ) ) {
            return $this->fail( $version_id, 'The screenshot did not arrive in time.' );
        }

        $attempts = (int) $version->screenshot_attempts + 1;
        $this->repository->update_screenshot( $version_id, array( 'screenshot_attempts' => $attempts ) );

        $software_id = $this->software_id();
        if ( $software_id === '' ) {
            return $this->fail( $version_id, 'Software ID not available.' );
        }

        $response = $this->client()->fetch( $software_id );
        if ( ! $this->is_success( $response['code'] ) ) {
            return $this->state( $this->repository->get_version( $version_id ) );
        }

        $image = $this->image_from( $response['data'] );
        if ( $image === '' ) {
            return $this->state( $this->repository->get_version( $version_id ) );
        }

        $written = $this->store->write( $version_id, $image );
        if ( empty( $written['ok'] ) ) {
            return $this->state( $this->repository->get_version( $version_id ) );
        }

        if ( $this->repository->find_version_by_screenshot_hash( (string) $written['hash'], $version_id ) > 0 ) {
            $this->store->delete( $version_id );

            return $this->state( $this->repository->get_version( $version_id ) );
        }

        $this->repository->update_screenshot(
            $version_id,
            array(
                'screenshot_status' => VersionConstant::SCREENSHOT_READY,
                'screenshot_file'   => (string) $written['file'],
                'screenshot_hash'   => (string) $written['hash'],
            )
        );

        return $this->state( $this->repository->get_version( $version_id ) );
    }

    public function delete( int $version_id ): void {
        $this->store->delete( $version_id );
    }

    public function state( ?object $version ): array {
        if ( $version === null ) {
            return array(
                'status' => VersionConstant::SCREENSHOT_FAILED,
                'url'    => '',
            );
        }

        $status = (string) $version->screenshot_status;
        $url    = '';

        if ( $status === VersionConstant::SCREENSHOT_READY ) {
            if ( $this->has_file( $version ) ) {
                $url = $this->store->url( (string) $version->screenshot_file );
            } else {
                $status = VersionConstant::SCREENSHOT_FAILED;
            }
        }

        return array(
            'status' => $status,
            'url'    => $url === '' ? '' : add_query_arg( 'v', $this->cache_key( $version ), $url ),
        );
    }

    private function has_file( object $version ): bool {
        return $this->store->exists( (string) $version->screenshot_file );
    }

    private function cache_key( object $version ): string {
        $hash = (string) $version->screenshot_hash;

        return $hash === '' ? (string) $version->id : substr( $hash, 0, 8 );
    }

    private function image_from( array $data ): string {
        foreach ( array( 'image', 'screenshot' ) as $key ) {
            if ( ! empty( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
                return $data[ $key ];
            }
        }

        return '';
    }

    private function is_exhausted( object $version ): bool {
        if ( (int) $version->screenshot_attempts >= self::MAX_ATTEMPTS ) {
            return true;
        }

        $requested_at = (string) $version->screenshot_requested_at;

        if ( $requested_at === '' ) {
            return false;
        }

        $requested = strtotime( $requested_at . ' GMT' );

        if ( $requested === false ) {
            return false;
        }

        return ( time() - $requested ) > self::MAX_WAIT_SECONDS;
    }

    private function is_success( int $code ): bool {
        return $code >= 200 && $code < 300;
    }

    private function fail( int $version_id, string $reason ): array {
        Helper::log( 'Version screenshot failed: ' . $reason );

        $this->repository->update_screenshot(
            $version_id,
            array( 'screenshot_status' => VersionConstant::SCREENSHOT_FAILED )
        );

        return $this->state( $this->repository->get_version( $version_id ) );
    }

    private function software_id(): string {
        try {
            if ( $this->wh_api_client === null ) {
                $this->wh_api_client = new RequestClient( $this->build_http_client() );
            }

            return (string) $this->get_software_id();
        } catch ( Throwable $e ) {
            Helper::log( 'Version screenshot could not resolve the software id: ' . $e->getMessage() );

            return '';
        }
    }

    private function client(): ScreenshotClient {
        if ( $this->client === null ) {
            $this->client = new ScreenshotClient( $this->build_http_client() );
        }

        return $this->client;
    }

    private function build_http_client(): Client {
        $helper = new Utils();
        $config = new Config();

        return new Client(
            $config->getConfigValue( 'base_proxy_rest_uri', HOSTINGER_WP_PROXY_API_URI ),
            array(
                Config::TOKEN_HEADER  => $helper::getApiToken(),
                Config::DOMAIN_HEADER => $helper->getHostInfo(),
                'Content-Type'        => 'application/json',
            )
        );
    }
}
