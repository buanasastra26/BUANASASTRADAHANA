<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Data\SectionData;

defined( 'ABSPATH' ) || exit;

class Structure {
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
     * @var RequestClient
     */
    private RequestClient $request_client;
    /**
     * @var string|null
     */
    private ?string $builder_type = null;

    /**
     * @param string      $brand_name
     * @param array       $website_type
     * @param string      $description
     * @param string|null $builder_type Specifies which builder type to use (gutenberg or elementor), overriding the default option value if provided
     */
    public function __construct( string $brand_name, array $website_type, string $description, ?string $builder_type = null ) {
        $this->brand_name   = $brand_name;
        $this->website_type = $website_type;
        $this->description  = $description;
        $this->builder_type = $builder_type;
    }

    /**
     * @param RequestClient $request_client
     *
     * @return void
     */
    public function set_request_client( RequestClient $request_client ): void {
        $this->request_client = $request_client;
    }

    public function generate_structure( array $pages = array() ): array {
        $website_types = array_map( fn( $t ) => $t === 'booking' ? 'business' : $t, $this->website_type );

        $params = array(
            'brand_name'   => $this->brand_name,
            'website_type' => $website_types[0] ?? 'business',
            'description'  => $this->description,
            'language'     => $this->get_site_locale(),
            'sections'     => SectionData::get_sections_for_website_type( $this->website_type, Helper::should_render_india_version() ),
        );

        if ( ! empty( $pages ) ) {
            $params['pages'] = $pages;
        }

        $structure = $this->request_client->post( '/v3/wordpress/plugin/builder/structure', $params );

        if ( ! empty( $pages ) ) {
            $structure = $this->reorder_pages( $structure, $pages );
        }

        $structure = $this->apply_section_rules( $structure );

        return $this->generate_unique_identifiers( $structure );
    }

    /**
     * @param array $page_data
     *
     * @return array
     */
    public function generate_content( array $page_data ): array {
        $website_types = array_map( fn( $t ) => $t === 'booking' ? 'business' : $t, $this->website_type );

        $params = array(
            'brand_name'        => $this->brand_name,
            'website_type'      => $website_types[0] ?? 'business',
            'description'       => $this->description,
            'image_with_prompt' => false,
            'language'          => $this->get_site_locale(),
            'pages'             => $this->format_page_data( $page_data ),
        );

        return $this->request_client->post( '/v3/wordpress/plugin/builder/ai-builder-v3', $params );
    }

    /**
     * @param $structure
     *
     * @return array
     * @throws \Exception
     */
    public function generate_builder_data( $structure ): array {
        foreach ( $structure as &$data ) {
            foreach ( $data['sections'] as &$section_data ) {
                $section_builder = new SectionBuilder( $section_data['section'], $this->builder_type );
                $section_builder->setHelper( new Helper() );

                $generate = $section_builder->generate();
                if ( ! empty( $generate ) ) {
                    $section_data['section']   = $section_builder->get_section();
                    $section_data['content']   = $section_builder->get_block_content();
                    $section_data['structure'] = $section_builder->get_block_used_elements();
                }
            }
        }

        $structure = $this->append_products_data( $structure );

        return $this->append_seo_data( $structure );
    }

    /**
     * @param array $structure
     * @param array $content
     *
     * @return array
     */
    public function merge_content( array $structure, array $content ): array {
        foreach ( $content['pages'] as $page => &$content_data ) {
            foreach ( $content_data['sections'] as $section_index => &$section_data ) {
                $section_data['html'] = $this->find_section_content( $page, $structure, $section_index );
            }
        }

        return $content;
    }

    /**
     * @param string $page
     * @param array  $structure
     * @param string $section_index
     *
     * @return string
     */
    private function find_section_content( string $page, array $structure, string $section_index ): string {

        foreach ( $structure as $structure_data ) {
            if ( $structure_data['page'] === $page ) {
                foreach ( $structure_data['sections'] as $section_data ) {
                    if ( $section_data['id'] === $section_index ) {
                        return ! empty( $section_data['content'] ) ? $section_data['content'] : '';
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param array $page_data
     *
     * @return array
     */
    private function format_page_data( array $page_data ): array {
        $formatted_data = array();

        foreach ( $page_data as $data ) {
            $sections = array();

            foreach ( $data['sections'] as $section_data ) {

                if ( empty( $section_data['structure'] ) ) {
                    continue;
                }

                $sections[ $section_data['id'] ] = array(
                    'type'     => $section_data['section'],
                    'elements' => $section_data['structure'],
                );
            }

            if ( ! empty( $sections ) ) {
                $formatted_data[ $data['page'] ] = array(
                    'sections' => $sections,
                );
            }
        }

        return $formatted_data;
    }

    /**
     * @param array $structure
     *
     * @return array
     */
    private function generate_unique_identifiers( array $structure ): array {
        $result = array();

        if ( get_option( 'hostinger_ai_woo', false ) ) {
            foreach ( $structure as $page => $sections ) {
                if ( strtolower( $page ) === 'shop' ) {
                    update_option( 'hostinger_ai_woo_shop_page_key', $page );
                    break;
                }
            }
        }

        foreach ( $structure as $page => $sections ) {
            $page_data = array(
                'page'     => $page,
                'sections' => array(),
            );

            foreach ( $sections as $section ) {
                $page_data['sections'][] = array(
                    'id'      => uniqid(),
                    'section' => $section,
                );
            }

            $result[] = $page_data;
        }

        return $result;
    }

    private function get_site_locale(): string {
        return Helper::get_site_locale();
    }

    private function append_seo_data( array $structure ): array {
        foreach ( $structure as &$page ) {

            if ( ! isset( $page['sections'] ) ) {
                $page['sections'] = array();
            }

            $page['sections'][] = array(
                'id'        => uniqid(),
                'section'   => 'seo',
                'structure' => array(
                    'seo_title' . uniqid()       => array(
                        'type'      => 'SEO Title',
                        'max_words' => 10,
                    ),
                    'seo_keywords' . uniqid()    => array(
                        'type'      => 'Comma separated list of 3 SEO keywords',
                        'max_words' => 10,
                    ),
                    'seo_description' . uniqid() => array(
                        'type'      => 'SEO Description',
                        'max_words' => 50,
                    ),
                ),
            );
        }

        return $structure;
    }

    private function append_products_data( array $structure ): array {
        if ( ! get_option( 'hostinger_ai_woo', false ) ) {
            return $structure;
        }

        $product_count    = 5;
        $product_elements = array();

        for ( $i = 1; $i <= $product_count; $i++ ) {
            $product_elements[ 'product_' . $i . '_image-' . uniqid() ]       = array(
                'type'      => 'Image',
                'max_words' => '5',
            );
            $product_elements[ 'product_' . $i . '_title-' . uniqid() ]       = array(
                'type'      => 'Title',
                'max_words' => '5',
            );
            $product_elements[ 'product_' . $i . '_description-' . uniqid() ] = array(
                'type'      => 'Description',
                'max_words' => '80',
            );
            $product_elements[ 'product_' . $i . '_price-' . uniqid() ]       = array(
                'type'      => 'Price number',
                'max_words' => '1',
            );
        }

        $ecommerce_page = array(
            'page'     => 'ecommercePagesGroup',
            'sections' => array(
                array(
                    'id'        => uniqid(),
                    'section'   => 'products',
                    'structure' => $product_elements,
                ),
            ),
        );

        $structure[] = $ecommerce_page;

        return $structure;
    }

    public function generate_page_structure( array $website_type, string $page_name ): array {
        $website_types = array_map( fn( $t ) => $t === 'booking' ? 'business' : $t, $website_type );

        $params = array(
            'brand_name'   => $this->brand_name,
            'website_type' => $website_types[0] ?? 'business',
            'description'  => $this->description,
            'language'     => $this->get_site_locale(),
            'sections'     => SectionData::get_sections_for_website_type( $website_type, Helper::should_render_india_version() ),
            'pages'        => array( $page_name ),
        );

        $structure = $this->request_client->post( '/v3/wordpress/plugin/builder/structure', $params );

        if ( empty( $structure ) ) {
            return array();
        }

        $structure = $this->apply_section_rules( $structure );

        $page_structure = array();

        if ( isset( $structure[ $page_name ] ) ) {
            $page_structure = array( $page_name => $structure[ $page_name ] );
        } else {
            $random_key     = array_rand( $structure );
            $page_structure = array( $random_key => $structure[ $random_key ] );
        }

        return $this->generate_unique_identifiers( $page_structure );
    }

    private function reorder_pages( array $structure, array $pages ): array {
        $ordered = array();

        foreach ( $pages as $page ) {
            if ( isset( $structure[ $page ] ) ) {
                $ordered[ $page ] = $structure[ $page ];
            }
        }

        foreach ( $structure as $page => $sections ) {
            if ( ! isset( $ordered[ $page ] ) ) {
                $ordered[ $page ] = $sections;
            }
        }

        return $ordered;
    }

    private function apply_section_rules( array $structure ): array {
        $structure = $this->promote_hero_video( $structure );
        $structure = $this->apply_homepage_section_pool( $structure );
        $structure = $this->enforce_homepage_singletons( $structure );

        return $structure;
    }

    private function promote_hero_video( array $structure ): array {
        foreach ( $structure as &$sections ) {
            if ( ! is_array( $sections ) ) {
                continue;
            }

            foreach ( $sections as &$section ) {
                if ( $section === 'hero' && wp_rand( 1, 100 ) <= 70 ) {
                    $section = 'hero-video';
                }
            }
        }

        return $structure;
    }

    private function apply_homepage_section_pool( array $structure ): array {
        if ( empty( $structure ) ) {
            return $structure;
        }

        $is_one_page = ( count( $this->website_type ) === 1 ) && ( $this->website_type[0] === 'landing page' );
        $pool        = SectionData::get_homepage_sections_pool( $this->website_type, $is_one_page );
        if ( empty( $pool ) ) {
            return $structure;
        }

        $home_key = array_key_first( $structure );
        if ( ! is_array( $structure[ $home_key ] ) ) {
            return $structure;
        }

        $structure[ $home_key ] = array_values(
            array_filter(
                $structure[ $home_key ],
                fn( $section ) => in_array( $section, $pool, true )
            )
        );

        return $structure;
    }

    private function enforce_homepage_singletons( array $structure ): array {
        if ( empty( $structure ) ) {
            return $structure;
        }

        $home_key = array_key_first( $structure );
        if ( ! is_array( $structure[ $home_key ] ) ) {
            return $structure;
        }

        $categories = SectionData::get_homepage_singletons();
        $seen       = array_fill_keys( array_keys( $categories ), false );
        $filtered   = array();

        foreach ( $structure[ $home_key ] as $section ) {
            $category = null;
            foreach ( $categories as $name => $members ) {
                if ( in_array( $section, $members, true ) ) {
                    $category = $name;
                    break;
                }
            }

            if ( $category === null ) {
                $filtered[] = $section;
                continue;
            }

            if ( ! $seen[ $category ] ) {
                $filtered[]        = $section;
                $seen[ $category ] = true;
            }
        }

        $structure[ $home_key ] = $filtered;
        return $structure;
    }
}
