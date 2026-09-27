<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Constants\BuilderType;

defined( 'ABSPATH' ) || exit;

class ShowcaseBuilder {
    private const CONTENT_ENDPOINT  = '/v3/wordpress/plugin/builder/ai-builder-v3';
    private const SHOWCASE_PAGE_KEY = 'showcase';

    /**
     * Section types that are full-page templates or non-visual, never showcased.
     */
    private const EXCLUDED_TYPES = array(
        'cart',
        'checkout',
        'shop',
        'my-account',
        'products',
        'seo',
    );

    private RequestClient $request_client;

    /**
     * @var array Human-readable identifiers of sections that failed to build.
     */
    private array $failed_sections = array();

    /**
     * Optional progress reporter; receives a single message string.
     *
     * @var callable|null
     */
    private $progress_callback = null;

    public function __construct( RequestClient $request_client ) {
        $this->request_client = $request_client;
    }

    /**
     * Register a callback to receive human-readable progress messages.
     *
     * @param callable $callback function( string $message ): void
     *
     * @return void
     */
    public function set_progress_callback( callable $callback ): void {
        $this->progress_callback = $callback;
    }

    private function report( string $message ): void {
        if ( $this->progress_callback !== null ) {
            ( $this->progress_callback )( $message );
        }
    }

    /**
     * Enumerate every variation file for a builder that has a base structure file
     * and is not an excluded type.
     *
     * @param string $builder gutenberg|elementor
     *
     * @return array<int, array{id:string,type:string,file:string}>
     */
    public function enumerate_variations( string $builder ): array {
        $dir   = get_stylesheet_directory() . DIRECTORY_SEPARATOR . 'blocks' . DIRECTORY_SEPARATOR . $builder . DIRECTORY_SEPARATOR;
        $files = glob( $dir . '*-[0-9]*.json' );

        if ( empty( $files ) ) {
            return array();
        }

        sort( $files );

        $variations = array();
        foreach ( $files as $file ) {
            $name = basename( $file, '.json' );          // e.g. hero-video-2
            $type = preg_replace( '/-\d+$/', '', $name ); // e.g. hero-video

            if ( in_array( $type, self::EXCLUDED_TYPES, true ) ) {
                continue;
            }

            if ( ! file_exists( $dir . $type . '.json' ) ) {
                continue; // No base structure file → cannot extract placeholders.
            }

            $variations[] = array(
                'id'   => uniqid(),
                'type' => $type,
                'file' => $file,
            );
        }

        return $variations;
    }

    /**
     * Build one showcase page containing every variation for a builder.
     *
     * @param string $builder      gutenberg|elementor
     * @param string $brand_name
     * @param string $description
     * @param array  $website_type
     * @param int    $chunk_size   Sections per content-API call.
     * @param int    $page_size    Sections per generated page (Elementor only; Gutenberg stays single-page).
     *
     * @return array{pages:array<int, array{page_id:int,url:string,title:string}>,variations:int,prepared:int,built:int,failed:array}
     */
    public function build( string $builder, string $brand_name, string $description, array $website_type, int $chunk_size, int $page_size = 25 ): array {
        $this->failed_sections = array();

        // Ensure SectionBuilder / ContentParser resolve the right format for this run.
        update_option( 'hostinger_ai_builder_type', $builder );

        $variations    = $this->enumerate_variations( $builder );
        $sections_data = array();

        $total = count( $variations );
        $this->report( sprintf( 'Preparing %d %s sections…', $total, $builder ) );

        $index = 0;
        foreach ( $variations as $variation ) {
            $index++;
            $label = $variation['type'] . ' (' . basename( $variation['file'] ) . ')';

            $section = $this->build_section( $variation, $builder );
            if ( $section === null ) {
                $this->failed_sections[] = $label;
                $this->report( sprintf( '[%d/%d] %s … skipped (no block)', $index, $total, $label ) );
                continue;
            }

            $sections_data[] = $section;
            $this->report( sprintf( '[%d/%d] %s … ok', $index, $total, $label ) );
        }

        $built = $this->generate_content_for_sections( $sections_data, $brand_name, $description, $website_type, $chunk_size );

        $this->report( sprintf( 'Writing pages with %d sections (%d per page)…', count( $built ), max( 1, $page_size ) ) );
        $pages = $this->build_pages( $builder, $built, $page_size );

        return array(
            'pages'      => $pages,
            'variations' => count( $variations ),
            'prepared'   => count( $sections_data ),
            'built'      => count( $built ),
            'failed'     => $this->failed_sections,
        );
    }

    /**
     * Split content-filled sections into per-page groups.
     *
     * Only Elementor is paginated (its editor cannot open a page with every variation);
     * Gutenberg always returns a single page. Multi-page runs carry an "(x of y)" title
     * suffix; a single page keeps the bare title.
     *
     * Pure: no WordPress or network calls, so the chunking/titling is unit-testable.
     *
     * @param string $builder   gutenberg|elementor
     * @param array  $sections  Content-filled sections (output of generate_content_for_sections()).
     * @param int    $page_size Sections per page; values below 1 are treated as 1.
     *
     * @return array<int, array{title:string,sections:array}>
     */
    public static function paginate_sections( string $builder, array $sections, int $page_size ): array {
        if ( empty( $sections ) ) {
            return array();
        }

        $base_title = 'Showcase — ' . ucfirst( $builder );

        if ( $builder !== BuilderType::ELEMENTOR ) {
            return array(
                array(
                    'title'    => $base_title,
                    'sections' => $sections,
                ),
            );
        }

        $chunks = array_chunk( $sections, max( 1, $page_size ) );
        $total  = count( $chunks );

        $pages = array();
        foreach ( $chunks as $i => $chunk ) {
            $pages[] = array(
                'title'    => $total > 1 ? sprintf( '%s (%d of %d)', $base_title, $i + 1, $total ) : $base_title,
                'sections' => $chunk,
            );
        }

        return $pages;
    }

    /**
     * @return array{id:string,type:string,content:string,structure:array}|null
     */
    private function build_section( array $variation, string $builder ): ?array {
        $section_builder = new SectionBuilder( $variation['type'], $builder );
        $section_builder->setHelper( new Helper() );
        $section_builder->set_variation( $variation['file'] );

        try {
            if ( ! $section_builder->generate() ) {
                return null;
            }
        } catch ( \Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Showcase: failed building section ' . $variation['file'] . ' - ' . $e->getMessage() );
            }
            return null;
        }

        return array(
            'id'        => $variation['id'],
            'type'      => $variation['type'],
            'label'     => basename( $variation['file'], '.json' ), // e.g. about-us-1
            'content'   => $section_builder->get_block_content(),
            'structure' => $section_builder->get_block_used_elements(),
        );
    }

    /**
     * Fill placeholders for every section by calling the content endpoint in chunks.
     *
     * @param array $sections   Output of build_section() entries.
     * @param int   $chunk_size
     *
     * @return array<int, array> Sections ready for ContentParser (with 'type','elements','html').
     */
    private function generate_content_for_sections( array $sections, string $brand_name, string $description, array $website_type, int $chunk_size ): array {
        if ( empty( $sections ) ) {
            return array();
        }

        $by_id = array();
        foreach ( $sections as $section ) {
            $by_id[ $section['id'] ] = $section;
        }

        $website_types = array_map( static fn( $t ) => $t === 'booking' ? 'business' : $t, $website_type );
        $primary       = $website_types[0] ?? 'business';
        $language      = Helper::get_site_locale();

        $built       = array();
        $chunk_count = max( 1, $chunk_size );
        $chunks      = array_chunk( $sections, $chunk_count );

        $content_total = 0;
        foreach ( $sections as $section ) {
            if ( ! empty( $section['structure'] ) ) {
                $content_total++;
            }
        }

        $this->report( sprintf( 'Generating content for %d sections (%d per chunk)…', $content_total, $chunk_count ) );

        $done = 0;
        foreach ( $chunks as $chunk ) {
            $payload_sections = array();
            foreach ( $chunk as $section ) {
                if ( empty( $section['structure'] ) ) {
                    continue;
                }
                $payload_sections[ $section['id'] ] = array(
                    'type'     => $section['type'],
                    'elements' => $section['structure'],
                );
            }

            if ( empty( $payload_sections ) ) {
                continue;
            }

            $params = array(
                'brand_name'        => $brand_name,
                'website_type'      => $primary,
                'description'       => $description,
                'image_with_prompt' => false,
                'language'          => $language,
                'pages'             => array(
                    self::SHOWCASE_PAGE_KEY => array(
                        'sections' => $payload_sections,
                    ),
                ),
            );

            $response = $this->request_client->post( self::CONTENT_ENDPOINT, $params );
            $returned = $response['pages'][ self::SHOWCASE_PAGE_KEY ]['sections'] ?? array();

            foreach ( array_keys( $payload_sections ) as $id ) {
                $done++;

                if ( empty( $returned[ $id ] ) ) {
                    $this->failed_sections[] = $by_id[ $id ]['type'] . ' (content)';
                    $this->report( sprintf( '[%d/%d] %s … no content', $done, $content_total, $by_id[ $id ]['type'] ) );
                    continue;
                }

                $section_content          = $returned[ $id ];
                $section_content['type']  = $by_id[ $id ]['type'];
                $section_content['label'] = $by_id[ $id ]['label'] ?? $by_id[ $id ]['type'];
                $section_content['html']  = $by_id[ $id ]['content'];

                $built[] = $section_content;
                $this->report( sprintf( '[%d/%d] %s … content ok', $done, $content_total, $by_id[ $id ]['type'] ) );
            }
        }

        return $built;
    }

    /**
     * Write one WordPress page per paginated group of sections.
     *
     * @param string $builder
     * @param array  $sections  Content-filled sections.
     * @param int    $page_size Sections per page (Elementor only).
     *
     * @return array<int, array{page_id:int,url:string,title:string}>
     */
    private function build_pages( string $builder, array $sections, int $page_size ): array {
        if ( empty( $sections ) ) {
            return array();
        }

        ImageManager::reset_image_tracking();
        ImageManager::set_current_page_key( self::SHOWCASE_PAGE_KEY );

        $pages = array();
        foreach ( self::paginate_sections( $builder, $sections, $page_size ) as $group ) {
            $page_id = $builder === BuilderType::ELEMENTOR
                ? $this->build_elementor_page( $group['title'], $group['sections'] )
                : $this->build_gutenberg_page( $group['title'], $group['sections'] );

            if ( ! $page_id ) {
                continue;
            }

            $pages[] = array(
                'page_id' => $page_id,
                'url'     => (string) get_permalink( $page_id ),
                'title'   => $group['title'],
            );
        }

        return $pages;
    }

    private function build_gutenberg_page( string $title, array $sections ): int {
        $content = $this->gutenberg_marker_style_block();
        foreach ( $sections as $section ) {
            $parser   = new ContentParser( $section, BuilderType::GUTENBERG );
            $content .= $this->add_gutenberg_marker( (string) $parser->output(), (string) ( $section['label'] ?? $section['type'] ?? '' ) );
        }

        $page_id = wp_insert_post(
            array(
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page',
            )
        );

        if ( ! $page_id || is_wp_error( $page_id ) ) {
            return 0;
        }

        update_post_meta( $page_id, '_wp_page_template', 'no-title' );
        update_post_meta( $page_id, '_hostinger_ai_generated', '1' );

        return (int) $page_id;
    }

    private function build_elementor_page( string $title, array $sections ): int {
        $elementor_data = array();
        foreach ( $sections as $section ) {
            $parser = new ContentParser( $section, BuilderType::ELEMENTOR );
            $output = $parser->output();
            if ( is_array( $output ) && ! empty( $output ) ) {
                $label  = (string) ( $section['label'] ?? $section['type'] ?? '' );
                $marker = $this->build_elementor_marker( $label );
                if ( ! empty( $marker ) ) {
                    $elementor_data[] = $marker;
                }

                $elementor_data = array_merge( $elementor_data, $output );
            }
        }

        $page_id = wp_insert_post(
            array(
                'post_title'   => $title,
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            )
        );

        if ( ! $page_id || is_wp_error( $page_id ) ) {
            return 0;
        }

        $elementor_json = wp_slash( json_encode( $elementor_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

        update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
        update_post_meta( $page_id, '_elementor_version', Helper::get_elementor_version() );
        update_post_meta( $page_id, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );
        update_post_meta( $page_id, '_elementor_data', $elementor_json );
        update_post_meta( $page_id, '_hostinger_ai_generated', '1' );

        return (int) $page_id;
    }

    /**
     * Inline styles for the small variation label shown in each section's top-right corner.
     */
    private function marker_style(): string {
        return 'position:absolute;top:6px;right:6px;z-index:9999;'
            . 'font:600 10px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;'
            . 'background:rgba(17,17,17,.85);color:#fff;padding:2px 6px;'
            . 'border-radius:4px;letter-spacing:.3px;pointer-events:none;';
    }

    /**
     * A one-off wp:html block that makes every showcase-section wrapper a positioning
     * context for its absolutely-positioned label. Emitted once at the top of the page.
     */
    private function gutenberg_marker_style_block(): string {
        return "<!-- wp:html -->\n"
            . '<style>.hostinger-showcase-section{position:relative}</style>' . "\n"
            . "<!-- /wp:html -->\n\n";
    }

    /**
     * Wrap a serialized Gutenberg section in a full-width constrained group (which
     * reproduces the root layout, so inner alignment is preserved) and prepend the
     * variation label as a wp:html block anchored to that wrapper's top-right corner.
     */
    private function add_gutenberg_marker( string $serialized, string $label ): string {
        if ( $serialized === '' || $label === '' ) {
            return $serialized;
        }

        $marker = "<!-- wp:html -->\n"
            . sprintf(
                '<span class="hostinger-showcase-label" style="%s">%s</span>',
                $this->marker_style(),
                esc_html( $label )
            ) . "\n"
            . "<!-- /wp:html -->\n\n";

        return "<!-- wp:group {\"align\":\"full\",\"className\":\"hostinger-showcase-section\",\"layout\":{\"type\":\"constrained\"}} -->\n"
            . '<div class="wp-block-group alignfull hostinger-showcase-section">' . "\n"
            . $marker
            . $serialized . "\n"
            . '</div>' . "\n"
            . "<!-- /wp:group -->\n\n";
    }

    /**
     * Inline styles for the standalone Elementor variation label. Rendered in normal flow
     * (not absolutely positioned) since it sits in its own block above the section.
     */
    private function elementor_marker_style(): string {
        return 'display:inline-block;'
            . 'font:600 10px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;'
            . 'background:rgba(17,17,17,.85);color:#fff;padding:2px 6px;'
            . 'border-radius:4px;letter-spacing:.3px;';
    }

    /**
     * Build a standalone top-level Elementor container holding an HTML widget that renders
     * the variation label. Inserted before each section (instead of inside it) so it never
     * perturbs the section's own layout.
     *
     * @param string $label
     *
     * @return array Top-level Elementor element, or empty array when there is no label.
     */
    private function build_elementor_marker( string $label ): array {
        if ( $label === '' ) {
            return array();
        }

        $marker_widget = array(
            'id'         => substr( md5( uniqid( 'sc', true ) ), 0, 7 ),
            'elType'     => 'widget',
            'widgetType' => 'html',
            'settings'   => array(
                'html' => sprintf(
                    '<span style="%s">%s</span>',
                    $this->elementor_marker_style(),
                    esc_html( $label )
                ),
            ),
            'elements'   => array(),
        );

        return array(
            'id'       => substr( md5( uniqid( 'sc', true ) ), 0, 7 ),
            'elType'   => 'container',
            'settings' => array(),
            'elements' => array( $marker_widget ),
        );
    }
}
