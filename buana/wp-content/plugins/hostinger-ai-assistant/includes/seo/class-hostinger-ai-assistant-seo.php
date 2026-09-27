<?php
/**
 * The file that defines all related to SEO
 *
 * @link       https://hostinger.com
 * @since      1.3.1
 *
 * @package    Hostinger_Ai_Assistant
 * @subpackage Hostinger_Ai_Assistant/admin
 */
class Hostinger_Ai_Assistant_Seo {
    const THEME_SEO_CLASS = '\\Hostinger\\AiTheme\\Builder\\Seo';

    /**
     * @var array<string, string> Normalized field name => Hostinger AI theme post meta key.
     */
    const THEME_META_MAP = array(
        'keywords'    => 'hostinger_ai_post_meta_seo_keywords',
        'description' => 'hostinger_ai_post_meta_description',
        'title'       => 'hostinger_ai_post_meta_title',
    );

    public function __construct() {
        add_action( 'template_redirect', array( $this, 'suppress_theme_meta_tags' ) );
        add_action( 'wp_head', array( $this, 'print_seo_meta_tags' ) );
        add_filter( 'wp_robots', array( $this, 'filter_wp_robots' ) );
        add_filter( 'document_title_parts', array( $this, 'filter_document_title' ) );
    }

    public function add_seo_meta_data( string $keywords, string $description, int $post_id ): void {
        $title = get_the_title( $post_id );
        update_post_meta( $post_id, 'hostinger_ai_assistant_seo_keywords', $keywords );
        update_post_meta( $post_id, 'hostinger_ai_assistant_seo_description', $description );
        update_post_meta( $post_id, 'hostinger_ai_assistant_seo_title', $title );

        $this->add_seo_meta_tags( $post_id );
    }

    public function add_seo_meta_tags( $post_id ): void {
        $seo_meta = $this->get_seo_meta( $post_id );

        foreach ( $this->make_resolver()->get_writable() as $provider ) {
            $guard_meta_key = $this->guard_meta_key( $provider->get_key() );

            if ( $guard_meta_key === '' ) {
                continue;
            }

            if ( ! empty( get_post_meta( $post_id, $guard_meta_key, true ) ) ) {
                continue;
            }

            $data   = $this->provider_data( $provider->get_key(), $seo_meta );
            $result = $provider->write( $post_id, $data );

            if ( ! $data->is_empty() && empty( $result['written'] ) ) {
                continue;
            }

            update_post_meta( $post_id, $guard_meta_key, true );
        }
    }

    public function print_seo_meta_tags(): void {
        $post_id = get_the_ID();

        if ( ! $post_id ) {
            return;
        }

        if ( $this->is_seo_plugin_active() ) {
            return;
        }

        $provider = new \Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers\HostingerProvider();

        $this->output_meta_tags( $provider->read( $post_id )->to_array(), $this->get_seo_meta( $post_id ) );
    }

    public function suppress_theme_meta_tags(): void {
        global $wp_filter;

        if ( ! class_exists( self::THEME_SEO_CLASS ) || ! isset( $wp_filter['wp_head'] ) ) {
            return;
        }

        foreach ( $wp_filter['wp_head']->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                if ( ! is_array( $callback['function'] ) ) {
                    continue;
                }

                if ( ! is_a( $callback['function'][0], self::THEME_SEO_CLASS ) ) {
                    continue;
                }

                remove_action( 'wp_head', $callback['function'], $priority );
            }
        }
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    public function filter_document_title( array $parts ): array {
        $post_id = get_the_ID();

        if ( ! $post_id || ! is_singular() ) {
            return $parts;
        }

        if ( $this->is_seo_plugin_active() ) {
            return $parts;
        }

        $title = ( new \Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers\HostingerProvider() )
            ->read( $post_id )
            ->get( 'seo_title' );

        if ( is_string( $title ) && $title !== '' ) {
            $parts['title'] = $title;
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $robots
     * @return array<string, mixed>
     */
    public function filter_wp_robots( array $robots ): array {
        $post_id = get_the_ID();

        if ( ! $post_id ) {
            return $robots;
        }

        if ( $this->is_seo_plugin_active() ) {
            return $robots;
        }

        $data = ( new \Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers\HostingerProvider() )->read( $post_id )->to_array();

        foreach ( array( 'noindex', 'nofollow', 'noarchive' ) as $directive ) {
            if ( ( $data['robots'][ $directive ] ?? null ) === true ) {
                $robots[ $directive ] = true;
            }
        }

        return $robots;
    }

    public function add_yoast_meta_tags( int $post_id, string $meta_description, string $keyword, string $meta_title ): void {
        $this->write_via_provider(
            new \Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers\YoastProvider(),
            $post_id,
            $meta_description,
            $keyword,
            $meta_title
        );
    }

    public function add_rank_math_meta_tags( int $post_id, string $meta_description, string $keyword, string $meta_title ): void {
        $this->write_via_provider(
            new \Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers\RankMathProvider(),
            $post_id,
            $meta_description,
            $keyword,
            $meta_title
        );
    }

    public function all_in_one_meta_tags( int $post_id, string $meta_description, string $keyword, string $meta_title ): void {
        $this->write_via_provider(
            new \Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Providers\AioseoProvider(),
            $post_id,
            $meta_description,
            $keyword,
            $meta_title
        );
    }

    private function provider_data( string $provider_key, array $seo_meta ): \Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\SeoData {
        $keyword = $provider_key === 'rank_math'
            ? $this->get_keywords( $seo_meta['keywords'], 4 )
            : $this->get_single_keyword( $seo_meta['keywords'] );

        $fields = array(
            'seo_title'        => (string) $seo_meta['title'],
            'meta_description' => (string) $seo_meta['description'],
            'focus_keyword'    => $keyword,
        );

        return \Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\SeoData::from_array(
            array_filter(
                $fields,
                static function ( string $value ): bool {
                    return $value !== '';
                }
            )
        );
    }

    protected function make_resolver(): \Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\ProviderResolver {
        return new \Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\ProviderResolver();
    }

    private function write_via_provider(
        \Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\Contracts\SeoProvider $provider,
        int $post_id,
        string $meta_description,
        string $keyword,
        string $meta_title
    ): void {
        $fields = array();

        if ( $meta_title !== '' ) {
            $fields['seo_title'] = $meta_title;
        }

        if ( $meta_description !== '' ) {
            $fields['meta_description'] = $meta_description;
        }

        if ( $keyword !== '' ) {
            $fields['focus_keyword'] = $keyword;
        }

        if ( empty( $fields ) ) {
            return;
        }

        $provider->write(
            $post_id,
            \Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo\SeoData::from_array( $fields )
        );
    }

    private function guard_meta_key( string $provider_key ): string {
        $keys = array(
            'yoast'     => 'hts_yoast_seo_tags_created',
            'rank_math' => 'hts_rank_seo_tags_created',
            'aioseo'    => 'hts_all_in_one_seo_tags_created',
        );

        return $keys[ $provider_key ] ?? '';
    }

    private function get_seo_meta( int $post_id ): array {
        return array(
            'keywords'                    => $this->get_meta_with_theme_fallback( $post_id, 'keywords', 'hostinger_ai_assistant_seo_keywords' ),
            'description'                 => $this->get_meta_with_theme_fallback( $post_id, 'description', 'hostinger_ai_assistant_seo_description' ),
            'title'                       => $this->get_meta_with_theme_fallback( $post_id, 'title', 'hostinger_ai_assistant_seo_title' ),
            'yoast_seo_tags_created'      => get_post_meta( $post_id, 'hts_yoast_seo_tags_created', true ),
            'rank_seo_tags_created'       => get_post_meta( $post_id, 'hts_rank_seo_tags_created', true ),
            'all_in_one_seo_tags_created' => get_post_meta( $post_id, 'hts_all_in_one_seo_tags_created', true ),
        );
    }

    private function get_meta_with_theme_fallback( int $post_id, string $field, string $meta_key ): string {
        $value = get_post_meta( $post_id, $meta_key, true );

        if ( is_string( $value ) && $value !== '' ) {
            return $value;
        }

        return $this->normalize_meta_value( get_post_meta( $post_id, self::THEME_META_MAP[ $field ], true ) );
    }

    private function normalize_meta_value( mixed $value ): string {
        if ( is_string( $value ) ) {
            return $value;
        }

        if ( is_array( $value ) ) {
            return implode( ', ', array_filter( array_map( 'trim', $value ) ) );
        }

        return '';
    }

    private function is_seo_plugin_active(): bool {
        $active = ! empty( $this->make_resolver()->get_active_keys() );

        return (bool) apply_filters( 'hostinger_ai_assistant_seo_plugin_active', $active );
    }

    private function output_meta_tags( array $data, array $seo_meta ): void {
        $keywords = $seo_meta['keywords'] ?? '';

        if ( is_string( $keywords ) && $keywords !== '' ) {
            echo '<meta name="keywords" content="' . esc_attr( $keywords ) . '" />' . "\n";
        }

        $simple_tags = array(
            'meta_description'    => array( 'name', 'description' ),
            'og_title'            => array( 'property', 'og:title' ),
            'og_description'      => array( 'property', 'og:description' ),
            'og_image'            => array( 'property', 'og:image' ),
            'twitter_title'       => array( 'name', 'twitter:title' ),
            'twitter_description' => array( 'name', 'twitter:description' ),
            'twitter_image'       => array( 'name', 'twitter:image' ),
        );

        $url_fields = array( 'og_image', 'twitter_image' );

        foreach ( $simple_tags as $field => $tag ) {
            $value = $data[ $field ] ?? '';

            if ( $value === '' ) {
                continue;
            }

            printf(
                '<meta %1$s="%2$s" content="%3$s" />' . "\n",
                esc_attr( $tag[0] ),
                esc_attr( $tag[1] ),
                in_array( $field, $url_fields, true ) ? esc_url( $value ) : esc_attr( $value )
            );
        }

        if ( ! empty( $data['canonical'] ) ) {
            echo '<link rel="canonical" href="' . esc_url( $data['canonical'] ) . '" />' . "\n";
        }
    }

    private function get_single_keyword( string $keywords ): string {
        $keywords = explode( ',', $keywords );
        if ( ! empty( $keywords ) ) {
            return trim( $keywords[0] );
        } else {
            return '';
        }
    }

    private function get_keywords( string $keywords, int $max_count = 1 ): string {
        $keywords = explode( ',', $keywords );
        $keywords = array_slice( $keywords, 0, $max_count );
        $keywords = array_map( 'trim', $keywords );
        $keywords = array_filter( $keywords );

        return implode( ', ', $keywords );
    }
}

$seo = new Hostinger_Ai_Assistant_Seo();
