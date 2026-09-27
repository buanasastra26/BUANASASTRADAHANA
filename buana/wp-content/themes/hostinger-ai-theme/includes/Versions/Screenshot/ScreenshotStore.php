<?php

namespace Hostinger\AiTheme\Versions\Screenshot;

use Hostinger\AiTheme\Builder\Helper;
use Hostinger\AiTheme\Versions\VersionConstant;

defined( 'ABSPATH' ) || exit;

class ScreenshotStore {
    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;
    private const ALLOWED_MIME = array(
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    );
    private const MAX_WIDTH = 480;

    public function write( int $version_id, string $encoded ): array {
        $bytes = $this->decode( $encoded );

        if ( $bytes === '' ) {
            return $this->failure( 'The screenshot payload could not be decoded.' );
        }

        if ( strlen( $bytes ) > self::MAX_IMAGE_BYTES ) {
            return $this->failure( sprintf( 'The screenshot payload is %d bytes.', strlen( $bytes ) ) );
        }

        $extension = $this->extension_for( $bytes );

        if ( $extension === '' ) {
            return $this->failure( 'The screenshot payload is not a supported image.' );
        }

        $directory = $this->directory();

        if ( $directory === '' ) {
            return $this->failure( 'The screenshot directory is not writable.' );
        }

        $this->delete( $version_id );

        $file = $this->file_name( $version_id, $extension );
        $path = $directory . $file;

        if ( ! $this->put_contents( $path, $bytes ) ) {
            return $this->failure( 'The screenshot could not be written to uploads.' );
        }

        $this->downscale( $path );

        return array(
            'ok'   => true,
            'file' => $file,
            'hash' => sha1( $bytes ),
        );
    }

    public function url( string $file ): string {
        if ( $file === '' ) {
            return '';
        }

        $baseurl = (string) ( wp_upload_dir( null, false )['baseurl'] ?? '' );

        if ( $baseurl === '' ) {
            return '';
        }

        return trailingslashit( $baseurl ) . VersionConstant::SCREENSHOT_DIR . '/' . $file;
    }

    public function exists( string $file ): bool {
        if ( $file === '' ) {
            return false;
        }

        $directory = $this->directory( false );

        return $directory !== '' && is_file( $directory . $file );
    }

    public function delete( int $version_id ): void {
        $directory = $this->directory( false );

        if ( $directory === '' ) {
            return;
        }

        foreach ( self::ALLOWED_MIME as $extension ) {
            $path = $directory . $this->file_name( $version_id, $extension );

            if ( is_file( $path ) ) {
                wp_delete_file( $path );
            }
        }
    }

    private function decode( string $encoded ): string {
        $encoded = trim( $encoded );

        if ( $encoded === '' ) {
            return '';
        }

        if ( str_starts_with( $encoded, 'data:' ) ) {
            $comma = strpos( $encoded, ',' );

            if ( $comma === false ) {
                return '';
            }

            $encoded = substr( $encoded, $comma + 1 );
        }

        $bytes = base64_decode( $encoded, true );

        return is_string( $bytes ) ? $bytes : '';
    }

    private function extension_for( string $bytes ): string {
        $info = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        if ( ! is_array( $info ) ) {
            return '';
        }

        return self::ALLOWED_MIME[ (string) ( $info['mime'] ?? '' ) ] ?? '';
    }

    private function file_name( int $version_id, string $extension ): string {
        return sprintf( 'version-%d.%s', $version_id, $extension );
    }

    private function directory( bool $create = true ): string {
        $basedir = (string) ( wp_upload_dir( null, false )['basedir'] ?? '' );

        if ( $basedir === '' ) {
            return '';
        }

        $directory = trailingslashit( $basedir ) . VersionConstant::SCREENSHOT_DIR . '/';

        if ( ! $create ) {
            return $directory;
        }

        if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
            return '';
        }

        return $directory;
    }

    private function put_contents( string $path, string $bytes ): bool {
        if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        }

        $filesystem = new \WP_Filesystem_Direct( false );
        $mode       = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;

        return (bool) $filesystem->put_contents( $path, $bytes, $mode );
    }

    private function downscale( string $path ): void {
        $editor = wp_get_image_editor( $path );

        if ( is_wp_error( $editor ) ) {
            return;
        }

        $size = $editor->get_size();

        if ( ! is_array( $size ) || (int) ( $size['width'] ?? 0 ) <= self::MAX_WIDTH ) {
            return;
        }

        if ( is_wp_error( $editor->resize( self::MAX_WIDTH, null, false ) ) ) {
            return;
        }

        $editor->save( $path );
    }

    private function failure( string $reason ): array {
        Helper::log( 'Version screenshot rejected: ' . $reason );

        return array(
            'ok'     => false,
            'reason' => $reason,
        );
    }
}
