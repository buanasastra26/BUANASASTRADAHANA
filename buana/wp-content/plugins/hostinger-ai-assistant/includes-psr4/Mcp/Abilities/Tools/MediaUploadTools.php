<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use Throwable;
use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class MediaUploadTools extends RestEndpointTool {
    private const MAX_REMOTE_FILE_SIZE = 32 * MB_IN_BYTES;
    private const DOWNLOAD_TIMEOUT     = 30;

    public function register(): void {
        wp_register_ability(
            'hostinger-ai/media-upload-from-url',
            array(
                'label'               => __( 'Upload Media From URL', 'hostinger-ai-assistant' ),
                'description'         => __( 'Sideload a file from an external URL into the WordPress media library, so it can be used as a featured image or inside post content. Any publicly reachable direct file URL is acceptable, including stock photo CDNs - the file is downloaded and stored locally, so the resulting attachment never depends on the external URL staying alive. The url must point at the file itself and return HTTP 200 with a file content type, not at a web page containing the image.', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'url'         => array(
                            'type'        => 'string',
                            'description' => __( 'Publicly reachable URL of the file to sideload into the media library.', 'hostinger-ai-assistant' ),
                        ),
                        'filename'    => array(
                            'type'        => 'string',
                            'description' => __( 'Optional. File name to store the file under, including extension. Defaults to the file name from the URL.', 'hostinger-ai-assistant' ),
                        ),
                        'title'       => array(
                            'type'        => 'string',
                            'description' => __( 'Optional. Attachment title.', 'hostinger-ai-assistant' ),
                        ),
                        'caption'     => array(
                            'type'        => 'string',
                            'description' => __( 'Optional. Attachment caption.', 'hostinger-ai-assistant' ),
                        ),
                        'description' => array(
                            'type'        => 'string',
                            'description' => __( 'Optional. Attachment description.', 'hostinger-ai-assistant' ),
                        ),
                        'alt_text'    => array(
                            'type'        => 'string',
                            'description' => __( 'Optional. Alternative text for images. Recommended for accessibility and SEO.', 'hostinger-ai-assistant' ),
                        ),
                        'post_id'     => array(
                            'type'        => 'integer',
                            'description' => __( 'Optional. ID of the post the attachment should be attached to.', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'url' ),
                ),
                'execute_callback'    => array( $this, 'upload_media_from_url' ),
                'permission_callback' => function () {
                    return current_user_can( 'upload_files' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'tool',
                    ),
                    'annotations'  => array(
                        'title'           => 'Upload Media From URL',
                        'readOnlyHint'    => false,
                        'destructiveHint' => false,
                        'idempotentHint'  => false,
                        'openWorldHint'   => true,
                    ),
                ),
            )
        );
    }

    public function upload_media_from_url( array $input ): WP_Error|array {
        $this->load_admin_dependencies();

        $url = isset( $input['url'] ) ? trim( (string) $input['url'] ) : '';

        if ( $url === '' ) {
            return new WP_Error( 'missing_url', __( 'A url is required.', 'hostinger-ai-assistant' ), array( 'status' => 400 ) );
        }

        $validated_url = $this->validate_remote_url( $url );
        if ( is_wp_error( $validated_url ) ) {
            return $validated_url;
        }

        $temp_file = download_url( $validated_url, self::DOWNLOAD_TIMEOUT );
        if ( is_wp_error( $temp_file ) ) {
            return new WP_Error(
                'download_failed',
                sprintf( /* translators: %s is the download error message */ __( 'Could not download the file: %s', 'hostinger-ai-assistant' ), $temp_file->get_error_message() ),
                array( 'status' => 400 )
            );
        }

        if ( filesize( $temp_file ) > $this->get_max_file_size() ) {
            wp_delete_file( $temp_file );

            return new WP_Error( 'file_too_large', __( 'File exceeds the maximum allowed size.', 'hostinger-ai-assistant' ), array( 'status' => 400 ) );
        }

        return $this->sideload_temp_file( $temp_file, $this->resolve_filename( $input, $validated_url, $temp_file ), $input );
    }

    private function load_admin_dependencies(): void {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    private function validate_remote_url( string $url ): WP_Error|string {
        $sanitized = esc_url_raw( $url );
        $scheme    = strtolower( (string) wp_parse_url( $sanitized, PHP_URL_SCHEME ) );

        if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
            return new WP_Error( 'invalid_url', __( 'Only http and https URLs are supported.', 'hostinger-ai-assistant' ), array( 'status' => 400 ) );
        }

        $validated = wp_http_validate_url( $sanitized );
        if ( ! $validated ) {
            return new WP_Error( 'invalid_url', __( 'The provided url is not a valid, publicly reachable URL.', 'hostinger-ai-assistant' ), array( 'status' => 400 ) );
        }

        if ( ! $this->host_resolves_to_public_ip( (string) wp_parse_url( $validated, PHP_URL_HOST ) ) ) {
            return new WP_Error( 'invalid_url', __( 'The provided url resolves to a private or reserved address.', 'hostinger-ai-assistant' ), array( 'status' => 400 ) );
        }

        return $validated;
    }

    /**
     * Older WordPress releases did not reject link-local (169.254.0.0/16) or CGNAT
     * ranges, and the core check never covers IPv6. The plugin supports those
     * releases, so the resolved addresses are re-checked here.
     */
    private function host_resolves_to_public_ip( string $host ): bool {
        if ( $host === '' ) {
            return false;
        }

        $addresses = filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : (array) gethostbynamel( $host );

        if ( empty( $addresses ) ) {
            return false;
        }

        foreach ( $addresses as $address ) {
            if ( ! filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return false;
            }
        }

        return true;
    }

    private function get_max_file_size(): int {
        $site_limit = (int) wp_max_upload_size();

        return $site_limit > 0 ? min( $site_limit, self::MAX_REMOTE_FILE_SIZE ) : self::MAX_REMOTE_FILE_SIZE;
    }

    private function sideload_temp_file( string $temp_file, string $filename, array $input ): WP_Error|array {
        $file_type = wp_check_filetype( $filename );
        if ( empty( $file_type['type'] ) || ! in_array( $file_type['type'], get_allowed_mime_types(), true ) ) {
            wp_delete_file( $temp_file );

            return new WP_Error(
                'invalid_file_type',
                sprintf( /* translators: %s is the file name */ __( 'File type of %s is not allowed on this site.', 'hostinger-ai-assistant' ), $filename ),
                array( 'status' => 400 )
            );
        }

        $parent_id = isset( $input['post_id'] ) ? intval( $input['post_id'] ) : 0;

        if ( $parent_id > 0 && get_post( $parent_id ) === null ) {
            wp_delete_file( $temp_file );

            return new WP_Error( 'invalid_post_id', __( 'The provided post_id does not exist.', 'hostinger-ai-assistant' ), array( 'status' => 400 ) );
        }

        $post_data = array(
            'post_title'   => ! empty( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : pathinfo( $filename, PATHINFO_FILENAME ),
            'post_content' => isset( $input['description'] ) ? sanitize_textarea_field( (string) $input['description'] ) : '',
            'post_excerpt' => isset( $input['caption'] ) ? sanitize_text_field( (string) $input['caption'] ) : '',
        );

        try {
            $attachment_id = media_handle_sideload(
                array(
                    'name'     => $filename,
                    'tmp_name' => $temp_file,
                    'type'     => $file_type['type'],
                ),
                $parent_id,
                null,
                $post_data
            );
        } catch ( Throwable $e ) {
            wp_delete_file( $temp_file );

            return new WP_Error( 'upload_failed', $e->getMessage(), array( 'status' => 500 ) );
        }

        if ( is_wp_error( $attachment_id ) ) {
            wp_delete_file( $temp_file );

            return $attachment_id;
        }

        if ( ! empty( $input['alt_text'] ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $input['alt_text'] ) );
        }

        return $this->build_attachment_response( intval( $attachment_id ) );
    }

    private function resolve_filename( array $input, string $url, string $temp_file ): string {
        $filename = '';

        if ( ! empty( $input['filename'] ) ) {
            $filename = sanitize_file_name( (string) $input['filename'] );
        } else {
            $filename = sanitize_file_name( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
        }

        if ( pathinfo( $filename, PATHINFO_EXTENSION ) !== '' ) {
            return $filename;
        }

        if ( $filename === '' ) {
            $filename = ! empty( $input['title'] ) ? sanitize_file_name( (string) $input['title'] ) : 'upload';
        }

        if ( $filename === '' ) {
            $filename = 'upload';
        }

        return $filename . '.' . $this->get_extension_from_mime_type( $this->detect_mime_type( $temp_file ) );
    }

    /**
     * The downloaded bytes are authoritative. Deriving the type from the remote
     * Content-Type would trust a header the other side controls, and would need a
     * second request that does not re-validate redirects.
     */
    private function detect_mime_type( string $temp_file ): string {
        $image_mime = wp_get_image_mime( $temp_file );
        if ( $image_mime ) {
            return $image_mime;
        }

        if ( function_exists( 'finfo_open' ) ) {
            $finfo = finfo_open( FILEINFO_MIME_TYPE );

            if ( $finfo ) {
                $mime_type = finfo_file( $finfo, $temp_file );

                if ( ! empty( $mime_type ) ) {
                    return $mime_type;
                }
            }
        }

        return '';
    }

    private function build_attachment_response( int $attachment_id ): array {
        $attachment = get_post( $attachment_id );
        $metadata   = wp_get_attachment_metadata( $attachment_id );

        return array(
            'id'        => $attachment_id,
            'title'     => $attachment instanceof WP_Post ? $attachment->post_title : '',
            'slug'      => $attachment instanceof WP_Post ? $attachment->post_name : '',
            'url'       => wp_get_attachment_url( $attachment_id ),
            'mime_type' => get_post_mime_type( $attachment_id ),
            'alt_text'  => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
            'caption'   => $attachment instanceof WP_Post ? $attachment->post_excerpt : '',
            'post_id'   => $attachment instanceof WP_Post ? intval( $attachment->post_parent ) : 0,
            'width'     => isset( $metadata['width'] ) ? intval( $metadata['width'] ) : null,
            'height'    => isset( $metadata['height'] ) ? intval( $metadata['height'] ) : null,
            'sizes'     => isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ? array_keys( $metadata['sizes'] ) : array(),
        );
    }

    private function get_extension_from_mime_type( string $mime_type ): string {
        foreach ( wp_get_mime_types() as $extensions => $type ) {
            if ( $type === $mime_type ) {
                return explode( '|', $extensions )[0];
            }
        }

        return 'bin';
    }
}
