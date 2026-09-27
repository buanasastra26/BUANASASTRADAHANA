<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Constants\GenerationConstant;
use Hostinger\AiTheme\Constants\PreviewImageConstant;
use Hostinger\AiTheme\Builder\Helper;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils as WpHelper;
use stdClass;

defined( 'ABSPATH' ) || exit;

class BlogBuilder {
    /**
     * @var string
     */
    private string $brand_name;
    /**
     * @var array
     */
    private array $website_type;
    /**
     * @var string
     */
    private string $description;

    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var ImageManager
     */
    private ImageManager $image_manager;

    private AffiliateBuilder $affiliate_builder;

    public function __construct( string $brand_name, array $website_type, string $description ) {
        $wp_helper      = new WpHelper();
        $config_handler = new Config();

        $this->client        = new Client(
            $config_handler->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI ),
            array(
                Config::TOKEN_HEADER  => $wp_helper::getApiToken(),
                Config::DOMAIN_HEADER => $wp_helper->getHostInfo(),
                'Content-Type'        => 'application/json',
            )
        );
        $this->image_manager = new ImageManager();

        $this->brand_name        = $brand_name;
        $this->website_type      = $website_type;
        $this->description       = $description;
        $this->affiliate_builder = new AffiliateBuilder();
    }

    public function generate_blog(): void {
        $blog_posts  = array();
        $used_titles = array();

        for ( $i = 0; $i < 2; $i++ ) {
            $blog_post = $this->generate_post( $used_titles );

            if ( ! empty( $blog_post->title ) && ! in_array( $blog_post->title, $used_titles, true ) ) {
                $used_titles[] = $blog_post->title;
                $blog_posts[]  = $blog_post;
            }
        }

        if ( $blog_posts ) {
            $created_blog_posts = array_map( array( $this, 'create_blog_post' ), $blog_posts );
            update_option( 'hostinger_ai_created_blog_posts', $created_blog_posts );
        }
    }

    public function generate_post( array $used_titles ): stdClass {
        $post_type  = 'blog_post';
        $length     = '150-300';
        $voice_tone = 'neutral';

        $data = array(
            'post_type'   => $post_type,
            'tone'        => $voice_tone,
            'length'      => $length,
            'description' => $this->description,
            'used_titles' => $used_titles,
            'language'    => $this->get_site_locale(),
        );

        $correlation_id = get_option( 'hts_correlation_id', null );

        $headers = array();
        if ( ! empty( $correlation_id ) ) {
            $headers['X-Correlation-ID'] = $correlation_id;
        }

        $response = $this->client->get( ImageManager::GENERATE_CONTENT_ACTION, $data, $headers );

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) || $response_code !== 200 ) {
            return new StdClass();
        }

        $generated_content = reset( json_decode( $response['body'] )->data );

        if ( isset( $generated_content->tags[0] ) && $generated_content->tags[0] !== '' ) {
            $this->image_manager->set_keyword( $generated_content->tags[0] );
            $image_data = $this->image_manager->get_unsplash_image_data();

            if ( ! empty( get_object_vars( $image_data ) ) ) {
                $generated_content->image_data = $image_data;
            }

            if ( isset( $generated_content->content ) ) {
                $generated_content->content .= $this->affiliate_builder->generate_shortcode( $generated_content->tags[0] );
            }
        }

        return $generated_content;
    }

    public function create_blog_post( StdClass $post ): int {
        $post_status = 'publish';
        $content     = $post->content ?? '';
        $title       = $post->title ?? '';
        $seo         = new Seo();

        $post_data = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $post_status,
            'post_type'    => 'post',
            'meta_input'   => array(
                GenerationConstant::META_KEY => '1',
            ),
        );

        $post_id = wp_insert_post( $post_data );
        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return 0;
        }

        if ( ! empty( $post->image_data->image ) ) {
            update_post_meta( $post_id, PreviewImageConstant::META_SLUG, $post->image_data->image );

            $this->image_manager->create_image_placeholder_attachment( $post_id );
        }

        $seo->load_seo_title( $post_id, $title );

        if ( ! empty( $post->meta_description ) ) {
            $seo->load_seo_description( $post_id, $post->meta_description );
        }

        if ( ! empty( $post->seo_keywords ) ) {
            if ( is_array( $post->seo_keywords ) ) {
                $keywords = implode( ', ', $post->seo_keywords );
            } else {
                $keywords = sanitize_text_field( $post->seo_keywords );
            }

            if ( ! empty ( $keywords ) ) {
                $seo->load_seo_keywords( $post_id, $keywords );
            }
        }

        $seo->add_seo_meta_tags( $post_id );

        return $post_id;
    }

    private function get_site_locale(): string {
        $wp_locale = get_option( 'hostinger_ai_selected_language', 'en_US' );

        return Helper::get_locale_name( $wp_locale );
    }
}
