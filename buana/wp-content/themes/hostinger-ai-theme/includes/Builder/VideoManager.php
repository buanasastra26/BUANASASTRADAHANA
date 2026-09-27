<?php

namespace Hostinger\AiTheme\Builder;

use stdClass;
use Hostinger\WpHelper\Utils;
use Hostinger\WpHelper\Utils as Helper;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\AiTheme\Rest\Endpoints;
use Hostinger\AiTheme\Constants\ApiRoutes;

defined( 'ABSPATH' ) || exit;

class VideoManager {
    use SoftwareIdTrait;

    private const USED_VIDEOS_OPTION = 'hostinger_ai_used_videos';
    private const FALLBACK_VIDEO_URL = 'https://videos.pexels.com/video-files/30014429/12877565_1920_1080_30fps.mp4';
    private const TARGET_VIDEO_WIDTH = 1280;
    private static array $video_list_cache = [];
    private static array $extracted_query_cache = [];

    public string $keyword;
    public string $keyword_slug = '';
    private Utils $helper;
    private Client $client;
    private Client $ai_client;
    private Config $config_handler;
    private DomainResolver $domain_resolver;
    private RequestClient $wh_api_client;

    public function __construct( string $keyword = '', ?DomainResolver $domain_resolver = null, ?Helper $helper = null ) {
        $this->keyword = $keyword;
        if ( ! empty( $this->keyword ) ) {
            $this->keyword_slug = sanitize_title( $this->keyword );
        }
        $this->helper         = $helper ?? new Helper();
        $this->config_handler = new Config();
        $this->domain_resolver = $domain_resolver ?? new DomainResolver( $this->helper );
        $this->client         = new Client(
            $this->config_handler->getConfigValue( 'base_proxy_rest_uri', HOSTINGER_WP_PROXY_API_URI ),
            [
                Config::TOKEN_HEADER  => $this->helper::getApiToken(),
                Config::DOMAIN_HEADER => $this->helper->getHostInfo(),
                'Content-Type'        => 'application/json'
            ]
        );
        $this->wh_api_client = new RequestClient( $this->client );
        $this->ai_client = new Client(
            $this->config_handler->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI ),
            [
                Config::TOKEN_HEADER  => $this->helper::getApiToken(),
                Config::DOMAIN_HEADER => $this->helper->getHostInfo(),
                'Content-Type'        => 'application/json'
            ]
        );
    }

    public function get_video_data( bool $random = false ): object {
        $video_list = $this->fetch_video_list();
        $video      = $this->pick_video_from_list( $video_list, $random );

        if ( ! property_exists( $video, 'url' ) || empty( $video->url ) ) {
            return $this->get_fallback_video();
        }

        return $video;
    }

    private function get_fallback_video(): object {
        return (object) [
            'url'         => self::FALLBACK_VIDEO_URL,
            'thumbnail'   => '',
            'description' => $this->keyword,
        ];
    }

    public function pick_video_from_list( array $video_list, bool $random = false ): object {
        $used_videos = get_option( self::USED_VIDEOS_OPTION, [] );

        if ( empty( $video_list ) ) {
            return new stdClass();
        }

        if ( ! empty( $random ) ) {
            shuffle( $video_list );
        }

        $all_used_urls = $this->get_all_used_video_urls( $used_videos );

        foreach ( $video_list as $video ) {
            if ( ! isset( $video->url ) || $video->url === '' ) {
                continue;
            }

            if ( ! in_array( $video->url, $all_used_urls, true ) ) {
                $used_videos[ $this->keyword_slug ][ $video->url ] = $video;
                update_option( self::USED_VIDEOS_OPTION, $used_videos, false );

                return $video;
            }
        }

        update_option( self::USED_VIDEOS_OPTION, [], false );

        foreach ( $video_list as $video ) {
            if ( isset( $video->url ) && $video->url !== '' ) {
                return $video;
            }
        }

        return new stdClass();
    }

    private function get_all_used_video_urls( array $used_videos ): array {
        $all_urls = [];

        foreach ( $used_videos as $keyword_videos ) {
            if ( is_array( $keyword_videos ) ) {
                $all_urls = array_merge( $all_urls, array_keys( $keyword_videos ) );
            }
        }

        return array_unique( $all_urls );
    }

    public function fetch_video_list(): array {
        try {
            $software_id = $this->get_software_id();

            if ( empty( $software_id ) ) {
                return [];
            }

            $search_query = $this->extract_search_query( $this->keyword );

            if ( empty( $search_query ) ) {
                $search_query = $this->keyword;
            }

            $cache_key = $software_id . '|' . $search_query;
            if ( isset( self::$video_list_cache[ $cache_key ] ) ) {
                return self::$video_list_cache[ $cache_key ];
            }

            $endpoint = ApiRoutes::INSTALLATIONS_BASE . $software_id . '/content/videos';

            $response = $this->client->get( $endpoint, [
                'query' => $search_query,
            ] );

            if ( is_wp_error( $response ) ) {
                self::$video_list_cache[ $cache_key ] = [];
                return [];
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            $response_data = json_decode( $response_body, true );

            if ( $response_code !== 200 ) {
                self::$video_list_cache[ $cache_key ] = [];
                return [];
            }

            $video_items = $response_data['data'] ?? [];

            if ( empty( $video_items ) || ! is_array( $video_items ) ) {
                self::$video_list_cache[ $cache_key ] = [];
                return [];
            }

            $videos = [];

            foreach ( $video_items as $item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }

                $video_data = $this->extract_video_data( $item );

                if ( ! empty( $video_data['url'] ) ) {
                    $videos[] = (object) [
                        'url'         => $video_data['url'],
                        'thumbnail'   => $video_data['thumbnail'] ?? '',
                        'description' => $this->keyword,
                    ];
                }
            }

            self::$video_list_cache[ $cache_key ] = $videos;

            return $videos;
        } catch ( \Exception $exception ) {
            error_log( 'Hostinger AI Theme - Error fetching video list: ' . $exception->getMessage() );
        }

        return [];
    }

    private function extract_search_query( string $description ): string {
        if ( empty( $description ) ) {
            return '';
        }

        if ( isset( self::$extracted_query_cache[ $description ] ) ) {
            return self::$extracted_query_cache[ $description ];
        }

        $result = $this->compute_search_query( $description );

        self::$extracted_query_cache[ $description ] = $result;

        return $result;
    }

    private function compute_search_query( string $description ): string {
        $short_fallback = $this->first_n_words( $description, 3 );

        $word_count = count( array_filter( preg_split( '/\s+/', trim( $description ) ) ) );
        if ( $word_count <= 3 ) {
            return $description;
        }

        try {
            $system_message = [
                'role'    => 'system',
                'content' => 'You extract stock video search keywords from a website description for searching Pexels. '
                             . 'Return 1-3 concise, visually searchable keywords that capture the main topic or theme — a business type, activity, scene, or subject. '
                             . 'ALWAYS return the keywords in English, even if the input text is in another language; translate the concept into the most common English search terms used on stock video sites. '
                             . 'Prefer concrete, filmable subjects over abstract terms. '
                             . 'Examples: "italian restaurant", "hair salon", "fitness training", "coffee shop", "wedding ceremony", "office teamwork", "nature hiking". '
                             . 'Output only the English keywords as lowercase plain text, with no quotes, punctuation, labels, or explanation.',
            ];

            $user_message = [
                'role'    => 'user',
                'content' => $description,
            ];

            $domain = $this->domain_resolver->get_current_domain();

            $request_body = [
                'domain'   => $domain,
                'messages' => [ $system_message, $user_message ],
            ];

            $response = $this->ai_client->post( Endpoints::GENERATE_BLOCKS_ENDPOINT, json_encode( $request_body ) );

            if ( is_wp_error( $response ) ) {
                return $short_fallback;
            }

            $response_code = wp_remote_retrieve_response_code( $response );

            if ( $response_code !== 200 ) {
                return $short_fallback;
            }

            $body    = json_decode( wp_remote_retrieve_body( $response ), true );
            $content = trim( $body['data']['content'] ?? '' );

            $content = trim( $content, '"\'' );
            $content = preg_replace( '/\s+/', ' ', $content );

            if ( empty( $content ) ) {
                return $short_fallback;
            }

            $words = explode( ' ', $content );
            if ( count( $words ) > 5 ) {
                $content = implode( ' ', array_slice( $words, 0, 5 ) );
            }

            return $content;
        } catch ( \Exception $e ) {
            return $short_fallback;
        }
    }

    private function first_n_words( string $text, int $n ): string {
        $words = array_filter( preg_split( '/\s+/', trim( $text ) ) );

        return implode( ' ', array_slice( $words, 0, $n ) );
    }

    private function extract_video_data( array $item ): array {
        return [
            'url'       => $this->extract_best_video_url( $item ),
            'thumbnail' => $item['imageLink'] ?? '',
        ];
    }

    private function extract_best_video_url( array $item ): string {
        if ( ! empty( $item['videoFiles'] ) && is_array( $item['videoFiles'] ) ) {
            $best_file  = null;
            $best_width = 0;

            foreach ( $item['videoFiles'] as $file ) {
                if ( empty( $file['videoLink'] ) ) {
                    continue;
                }

                $width = (int) ( $file['width'] ?? 0 );

                if ( $best_file === null || abs( $width - self::TARGET_VIDEO_WIDTH ) < abs( $best_width - self::TARGET_VIDEO_WIDTH ) ) {
                    $best_file  = $file['videoLink'];
                    $best_width = $width;
                }
            }

            if ( ! empty( $best_file ) ) {
                return $best_file;
            }
        }

        return $item['video_url'] ?? $item['videoLink'] ?? '';
    }
}
