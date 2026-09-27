<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Admin\Hooks;
use Hostinger\AiTheme\Constants\GenerationConstant;
use Hostinger\AiTheme\Constants\PreviewImageConstant;
use stdClass;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils;
use Hostinger\WpHelper\Utils as Helper;

defined( 'ABSPATH' ) || exit;

class ImageManager {
    public const GENERATE_CONTENT_IMAGES_ACTION = '/v3/wordpress/plugin/search-images';
    public const GENERATE_CONTENT_ACTION = '/v3/wordpress/plugin/generate-content';
    public const GET_UNSPLASH_IMAGE_ACTION = '/v3/wordpress/plugin/download-image';

    private const USED_IMAGES_OPTION = 'hostinger_ai_used_images';
    private const IMAGE_DATA_TRANSIENT_PREFIX = 'image_data_';

    /**
     * @var string
     */
    public string $keyword;
    /**
     * @var string
     */
    public string $keyword_slug = '';
    /**
     * @var Helper
     */
    private Utils $helper;
    /**
     * @var Client
     */
    private Client $client;
    /**
     * @var DomainResolver
     */
    private DomainResolver $domain_resolver;

    /**
     * @param string $keyword
     */
    public function __construct( string $keyword = '', ?DomainResolver $domain_resolver = null )
    {
        $this->keyword        = $keyword;
        if(!empty($this->keyword)) {
            $this->keyword_slug = sanitize_title($this->keyword);
        }
        $this->helper          = new Helper();
        $this->domain_resolver = $domain_resolver ?? new DomainResolver( $this->helper );
        $config_handler        = new Config();
        $this->client         = new Client( $config_handler->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI ), [
            Config::TOKEN_HEADER  => $this->helper::getApiToken(),
            Config::DOMAIN_HEADER => $this->helper->getHostInfo(),
            'Content-Type' => 'application/json'
        ] );
    }

    public function set_keyword(string $keyword): void
    {
        $this->keyword = $keyword;
        $this->keyword_slug = sanitize_title($this->keyword);
    }

    /**
     * @return object
     */
    public function get_unsplash_image_data( bool $random = false ): object {
        $transient_key = self::IMAGE_DATA_TRANSIENT_PREFIX . $this->keyword_slug;

        if ( get_transient( $transient_key ) ) {
            $image_list = get_transient( $transient_key );
        } else {
            $image_list = $this->fetch_image_list();

            if ( !empty( $image_list ) ) {
                set_transient( $transient_key, $image_list, DAY_IN_SECONDS );
            }
        }

        return $this->pick_image_from_list( $image_list, $random );
    }

    /**
     * @param array $image_list
     * @param bool  $random
     *
     * @return mixed|stdClass|void
     */
    public function pick_image_from_list( array $image_list, bool $random = false ) {
        $used_images = get_option( self::USED_IMAGES_OPTION, [] );

        if( empty( $image_list ) ) {
            return new stdClass();
        }

        if ( ! empty( $random ) ) {
            shuffle( $image_list );
        }

        $all_used_urls = $this->get_all_used_image_urls( $used_images );

        foreach( $image_list as $image ) {
            if ( empty( $image->image ) ) {
                continue;
            }

            if ( ! in_array( $image->image, $all_used_urls, true ) ) {
                $used_images[$this->keyword_slug][$image->image] = $image;

                update_option( self::USED_IMAGES_OPTION, $used_images, false );

                return $image;
            }
        }

        update_option( self::USED_IMAGES_OPTION, [], false );

        foreach ( $image_list as $image ) {
            if ( isset( $image->image ) && $image->image !== '' ) {
                return $image;
            }
        }

        return end( $image_list );
    }

    private function get_all_used_image_urls( array $used_images ): array {
        $all_urls = [];

        foreach ( $used_images as $keyword_images ) {
            if ( is_array( $keyword_images ) ) {
                $all_urls = array_merge( $all_urls, array_keys( $keyword_images ) );
            }
        }

        return array_unique( $all_urls );
    }

    /**
     * @return array
     */
    public function fetch_image_list(): array {
        try {
            $host = $this->domain_resolver->get_current_domain();

            $response = $this->client->post( self::GENERATE_CONTENT_IMAGES_ACTION, json_encode( [
                'domain'      => $host,
                'description' => $this->keyword,
                'limit'       => 20,
            ] ) );

            if ( is_wp_error( $response ) ) {
                error_log( 'Hostinger AI Theme - Image search request failed for keyword "' . $this->keyword . '": ' . $response->get_error_message() );

                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Hostinger AI Theme - HTTP request error: ' . print_r( $response, true ) );
                }

                return [];
            }

            $response_code      = wp_remote_retrieve_response_code( $response );
            $response_body      = wp_remote_retrieve_body( $response );
            $response_data_body = json_decode( $response_body, true );
            $response_data      = $response_data_body['data']['list'] ?? [];

            if ( empty( $response_data ) ) {
                error_log( 'Hostinger AI Theme - Empty image list for keyword "' . $this->keyword . '" (response code ' . $response_code . ')' );

                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Hostinger AI Theme - Empty image list response: ' . print_r( $response, true ) );
                }

                return [];
            }

            if ( $response_code !== 200 ) {
                error_log( 'Hostinger AI Theme - Image search returned response code ' . $response_code . ' for keyword "' . $this->keyword . '"' );

                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Hostinger AI Theme: ' . print_r( $response_data, true ) );
                }

                return [];
            }

            return array_map( static function ( $item ) {
                return (object)[
                    'image'               => $item['photo_image_url'] ?? '',
                    'alt_description'     => $item['description'] ?? '',
                    'suitable_hero_image' => ! empty( $item['suitable_hero_image'] ),
                ];
            }, $response_data );
        } catch ( Exception $exception ) {
            error_log( 'Hostinger AI Theme - Error fetching image list: ' . $exception->getMessage() );
        }

        return [];
    }

    /**
     * @param $url
     * @param $image_size_data
     *
     * @return string
     */
    public function modify_image_url( $url, $element_structure = null ): string {
        $url = $url . '?q=85';
        $parsed_url = parse_url( $url );

        parse_str( $parsed_url['query'], $query_params );

        if ( ! empty( $element_structure['image_size'] ) ) {

            if ( ! empty( $element_structure['image_size']['width'] ) ) {
                $query_params['w'] = $element_structure['image_size']['width'];
            }

            if ( ! empty( $element_structure['image_size']['height'] ) ) {
                $query_params['h'] = $element_structure['image_size']['height'];
            }

            if ( ! empty( $element_structure['image_size']['crop'] ) ) {
                $query_params['fit'] = 'crop';
            }

        }

        $new_query = http_build_query( $query_params );

        return $parsed_url['scheme'] . '://' . $parsed_url['host'] . $parsed_url['path'] . '?' . $new_query;
    }

    public function create_image_placeholder_attachment( int $post_id, bool $set_featured_image = false ): bool {
        $attachment = array(
            'post_title' => 'External Image For post: ' . $post_id,
            'post_content' => '',
            'post_status' => 'inherit',
            'meta_input' => array(
                GenerationConstant::META_KEY => '1',
            ),
        );

        $attach_id = wp_insert_attachment( $attachment, false, $post_id );

        if ( is_wp_error( $attach_id ) ) {
            return false;
        }

        update_post_meta( $post_id, PreviewImageConstant::ATTACHMENT_ID, $attach_id );
        update_post_meta( $attach_id, PreviewImageConstant::POST_ID, $post_id );

        if ( $set_featured_image ) {
            set_post_thumbnail( $post_id, $attach_id );
        }

        return true;
    }

    public function get_attachments_by_meta_value( string $meta_key, string $meta_value ): array {
        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'any',
            'meta_key'       => $meta_key,
            'meta_value'     => $meta_value,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        return get_posts($args);
    }

    public function delete_attachments_by_meta_value( string $meta_key, string $meta_value ): bool {
        $attachments = $this->get_attachments_by_meta_value( $meta_key, $meta_value );

        if ( empty($attachments) ) {
            return false;
        }

        foreach ( $attachments as $attachment_id ) {
            wp_delete_attachment( $attachment_id, true );
        }

        return true;
    }

    public function clean_external_image_data( int $post_id ) : void {
        delete_post_meta( $post_id, PreviewImageConstant::META_SLUG );
        delete_post_meta( $post_id, PreviewImageConstant::ATTACHMENT_ID );

        $this->delete_attachments_by_meta_value( PreviewImageConstant::POST_ID, $post_id );
    }

    /**
     * Per-page image usage tracking to avoid duplicates (matches WB's AiImageSelectionUtils).
     */
    private static array $used_images_per_page = array();
    private static array $first_image_per_page = array();
    private static array $image_usage_counts = array();
    private static string $current_page_key = 'default';

    public static function reset_image_tracking(): void {
        self::$used_images_per_page = array();
        self::$first_image_per_page = array();
        self::$image_usage_counts   = array();
        self::$current_page_key     = 'default';
    }

    public static function set_current_page_key( string $key ): void {
        self::$current_page_key = $key;
    }

    /**
     * Get an image from the existing hapi image search (which uses Milvus vector search).
     * When prefer_hero is true, tries to filter for images flagged as suitable_hero_image;
     * falls back to the full list if none are flagged (e.g. when all return suitable_hero_image=false).
     * Uses per-page deduplication to avoid repeating images across sections.
     */
    public function get_image_data( bool $prefer_hero = false ): object {
        $transient_key = self::IMAGE_DATA_TRANSIENT_PREFIX . $this->keyword_slug;

        if ( get_transient( $transient_key ) ) {
            $image_list = get_transient( $transient_key );
        } else {
            $image_list = $this->fetch_image_list();

            if ( ! empty( $image_list ) ) {
                set_transient( $transient_key, $image_list, DAY_IN_SECONDS );
            }
        }

        if ( empty( $image_list ) ) {
            return new stdClass();
        }

        if ( $prefer_hero ) {
            $hero_list = array_filter( $image_list, function ( $item ) {
                return ! empty( $item->suitable_hero_image );
            } );

            if ( ! empty( $hero_list ) ) {
                $image_list = array_values( $hero_list );
            }
        }

        return $this->pick_unused_image( $image_list );
    }

    private function pick_unused_image( array $image_list ): object {
        $page_key = $this->get_current_page_key();
        $used     = self::$used_images_per_page[ $page_key ] ?? array();
        $all_used = $this->get_all_used_tracking_urls();

        // First pass: find image not used on any page.
        foreach ( $image_list as $image ) {
            if ( empty( $image->image ) ) {
                continue;
            }
            if ( ! in_array( $image->image, $all_used, true ) ) {
                $this->track_image_usage( $page_key, $image->image );
                return $image;
            }
        }

        // Second pass: find image not used on this page.
        foreach ( $image_list as $image ) {
            if ( empty( $image->image ) ) {
                continue;
            }
            if ( ! in_array( $image->image, $used, true ) ) {
                $this->track_image_usage( $page_key, $image->image );
                return $image;
            }
        }

        return $this->pick_least_used_image( $page_key, $image_list );
    }

    /**
     * Once every candidate has been used, keep rotating through the list instead of
     * handing the same first image to every remaining slot.
     */
    private function pick_least_used_image( string $page_key, array $image_list ): object {
        $least_used = null;
        $lowest     = PHP_INT_MAX;

        foreach ( $image_list as $image ) {
            if ( empty( $image->image ) ) {
                continue;
            }

            $usage = self::$image_usage_counts[ $image->image ] ?? 0;

            if ( $usage < $lowest ) {
                $lowest     = $usage;
                $least_used = $image;
            }
        }

        if ( $least_used === null ) {
            return new stdClass();
        }

        $this->track_image_usage( $page_key, $least_used->image );

        return $least_used;
    }

    private function track_image_usage( string $page_key, string $url ): void {
        if ( ! isset( self::$used_images_per_page[ $page_key ] ) ) {
            self::$used_images_per_page[ $page_key ] = array();
            self::$first_image_per_page[ $page_key ] = $url;
        }
        self::$used_images_per_page[ $page_key ][] = $url;

        self::$image_usage_counts[ $url ] = ( self::$image_usage_counts[ $url ] ?? 0 ) + 1;
    }

    private function get_all_used_tracking_urls(): array {
        $all = array();
        foreach ( self::$used_images_per_page as $urls ) {
            $all = array_merge( $all, $urls );
        }
        return array_unique( $all );
    }

    private function get_current_page_key(): string {
        return self::$current_page_key;
    }
}
