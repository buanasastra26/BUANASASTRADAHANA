<?php

namespace Hostinger\AiTheme\Cli;

use Hostinger\AiTheme\Builder\ShowcaseBuilder;
use Hostinger\AiTheme\Builder\WebsiteBuilder;
use Hostinger\AiTheme\Builder\WebsiteTypeDetector;
use Hostinger\AiTheme\Constants\BuilderType;
use Hostinger\WpHelper\Utils;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Generates a single preview page per builder containing every section variation.
 */
class ShowcaseCommand {
    private WebsiteBuilder $website_builder;
    private ShowcaseBuilder $showcase_builder;
    private WebsiteTypeDetector $website_type_detector;

    public function __construct(
        WebsiteBuilder $website_builder,
        ShowcaseBuilder $showcase_builder,
        WebsiteTypeDetector $website_type_detector
    ) {
        $this->website_builder       = $website_builder;
        $this->showcase_builder      = $showcase_builder;
        $this->website_type_detector = $website_type_detector;
    }

    /**
     * Build showcase page(s).
     *
     * ## OPTIONS
     *
     * --brand=<brand>
     * : Brand name.
     *
     * --description=<description>
     * : Business description used for content + color generation.
     *
     * [--builder=<builder>]
     * : gutenberg, elementor, or both. Default: gutenberg.
     *
     * [--website-type=<type>]
     * : Website type (e.g. business, online store). Auto-detected when omitted.
     *
     * [--chunk-size=<n>]
     * : Sections per content-API call. Default: 10.
     *
     * [--page-size=<n>]
     * : Sections per generated page (Elementor only; Gutenberg stays single-page). Default: 25.
     *
     * [--dry-run]
     * : Detect website type and count variations without generating content.
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke( array $args, array $assoc_args ): void {
        $brand       = (string) ( $assoc_args['brand'] ?? '' );
        $description = (string) ( $assoc_args['description'] ?? '' );

        if ( $brand === '' || $description === '' ) {
            WP_CLI::error( '--brand and --description are required.' );
        }

        if ( empty( Utils::getApiToken() ) ) {
            WP_CLI::error( 'No API token found. Configure .api_token before running (see hostinger-ai-theme-testing).' );
        }

        $chunk_size = (int) ( $assoc_args['chunk-size'] ?? 10 );
        $page_size  = max( 1, (int) ( $assoc_args['page-size'] ?? 25 ) );
        $dry_run    = isset( $assoc_args['dry-run'] );
        $builders   = $this->resolve_builders( (string) ( $assoc_args['builder'] ?? BuilderType::GUTENBERG ) );

        $website_type = $this->resolve_website_type( $assoc_args, $description, $brand );
        WP_CLI::log( 'Website type: ' . implode( ', ', $website_type ) );

        update_option( 'hostinger_ai_website_type', $website_type );
        update_option( 'hostinger_ai_brand_name', $brand );
        update_option( 'blogname', $brand );
        update_option( 'hostinger_ai_description', $description );

        if ( $dry_run ) {
            foreach ( $builders as $builder ) {
                $count = count( $this->showcase_builder->enumerate_variations( $builder ) );
                WP_CLI::log( sprintf( '[dry-run] %s: %d variations', $builder, $count ) );
            }
            WP_CLI::success( 'Dry run complete.' );
            return;
        }

        WP_CLI::log( 'Generating colors…' );
        $this->website_builder->generate_colors( $description );
        update_option( 'hostinger_ai_version', time(), true );

        $this->showcase_builder->set_progress_callback( static function ( string $message ): void {
            WP_CLI::log( '  ' . $message );
        } );

        foreach ( $builders as $builder ) {
            WP_CLI::log( "Building {$builder} showcase…" );

            $result = $this->showcase_builder->build( $builder, $brand, $description, $website_type, $chunk_size, $page_size );

            if ( empty( $result['pages'] ) ) {
                WP_CLI::warning( "Failed to build {$builder} page (no sections produced)." );
                continue;
            }

            if ( ! empty( $result['failed'] ) ) {
                WP_CLI::warning( sprintf(
                    '%d sections failed: %s',
                    count( $result['failed'] ),
                    implode( ', ', $result['failed'] )
                ) );
            }

            WP_CLI::success( sprintf(
                '%s: %d enumerated, %d prepared, %d content-filled → %d page(s)',
                $builder,
                $result['variations'],
                $result['prepared'],
                $result['built'],
                count( $result['pages'] )
            ) );

            foreach ( $result['pages'] as $page ) {
                WP_CLI::log( sprintf( '  %s → %s', $page['title'], $page['url'] ) );
            }
        }
    }

    /**
     * @return array<int, string> Builders to build, after validation + availability checks.
     */
    private function resolve_builders( string $arg ): array {
        switch ( $arg ) {
            case 'both':
                $builders = array( BuilderType::GUTENBERG, BuilderType::ELEMENTOR );
                break;
            case BuilderType::ELEMENTOR:
                $builders = array( BuilderType::ELEMENTOR );
                break;
            case BuilderType::GUTENBERG:
                $builders = array( BuilderType::GUTENBERG );
                break;
            default:
                WP_CLI::error( "Invalid --builder: {$arg}. Use gutenberg, elementor, or both." );
                return array(); // unreachable; WP_CLI::error halts.
        }

        if ( in_array( BuilderType::ELEMENTOR, $builders, true ) && ! did_action( 'elementor/loaded' ) ) {
            WP_CLI::warning( 'Elementor is not active; skipping elementor builder.' );
            $builders = array_values( array_filter( $builders, static fn( $b ) => $b !== BuilderType::ELEMENTOR ) );
        }

        if ( empty( $builders ) ) {
            WP_CLI::error( 'No buildable builder selected.' );
        }

        return $builders;
    }

    /**
     * Resolution order: --website-type arg → auto-detect → fallback "business".
     *
     * @return array<int, string>
     */
    private function resolve_website_type( array $assoc_args, string $description, string $brand ): array {
        if ( ! empty( $assoc_args['website-type'] ) ) {
            return array( strtolower( sanitize_text_field( (string) $assoc_args['website-type'] ) ) );
        }

        try {
            $types = $this->website_type_detector->detect( $description, $brand );
            if ( ! empty( $types ) ) {
                $types = array_map( 'strtolower', $types );
                WP_CLI::log( 'Detected website type: ' . implode( ', ', $types ) );
                return $types;
            }
            WP_CLI::warning( 'Website type detection returned nothing; using "business".' );
        } catch ( \Exception $e ) {
            WP_CLI::warning( 'Website type detection failed (' . $e->getMessage() . '); using "business".' );
        }

        return array( 'business' );
    }
}
