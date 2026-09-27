<?php

namespace Hostinger\AiTheme\Builder;

use Exception;
use Hostinger\AiTheme\Constants\GenerationConstant;
use Hostinger\AiTheme\Data\HostingerEcommerceHelper;
use Hostinger\AiTheme\Data\WebsiteTypeHelper;

defined( 'ABSPATH' ) || exit;

class NavigationBuilder {
    /**
     * @var array
     */
    private array $pages;

    /**
     * @param array $pages
     */
    public function __construct( array $pages )
    {
        $this->pages = $pages;
    }

    /**
     * @return int The created navigation menu (wp_navigation) post id.
     */
    public function updateMenus(): int {
        $menu_id = $this->createMenu();

        $this->updateTemplatePart( 'header', $menu_id );
        $this->updateTemplatePart( 'footer', $menu_id );

        return $menu_id;
    }

    /**
     * @return int
     */
    private function createMenu(): int {
        $content = $this->generateMenuItems();
        $menu_id = wp_insert_post(
            array(
                'post_title'   => __('Main menu', 'hostinger-ai-theme'),
                'post_type'    => 'wp_navigation',
                'post_status'  => 'publish',
                'post_content' => $content,
                'meta_input'   => array(
	                GenerationConstant::META_KEY => '1',
                ),
            ),
            false,
            false);

        if ( is_wp_error( $menu_id ) ) {
            throw new Exception( 'Error creating menu: ' . $menu_id->get_error_message() );
        }

        return $menu_id;
    }

    /**
     * @return string
     */
    private function generateMenuItems(): string {
        $html = '';

        if ( !empty( $this->pages ) ) {
            foreach( $this->pages as $page_data ) {
                $html .= $this->generate_menu_item( $page_data, true );
            }
        }

        return $html;
    }

    private function generate_menu_item( array $page_data, bool $is_top_level ): string {
        $link_meta = $this->get_link_meta( $page_data );
        $children  = is_array( $page_data['children'] ?? null ) ? $page_data['children'] : array();
        $children  = array_values( array_filter( $children, 'is_array' ) );

        if ( empty( $children ) ) {
            $link_meta['isTopLevelLink'] = $is_top_level;

            return '<!-- wp:navigation-link ' . wp_json_encode( $link_meta, JSON_UNESCAPED_UNICODE ) . ' /-->';
        }

        $link_meta['isTopLevelItem'] = $is_top_level;
        $html                        = '<!-- wp:navigation-submenu '
            . wp_json_encode( $link_meta, JSON_UNESCAPED_UNICODE ) . ' -->';

        foreach ( $children as $child ) {
            if ( is_array( $child ) ) {
                $html .= $this->generate_menu_item( $child, false );
            }
        }

        return $html . '<!-- /wp:navigation-submenu -->';
    }

    private function get_link_meta( array $page_data ): array {
        $label = (string) ( $page_data['title'] ?? '' );

        if ( isset( $page_data['product_category'], $page_data['term_id'], $page_data['url'] ) ) {
            return array(
                'label' => $label,
                'type'  => 'product_cat',
                'id'    => (int) $page_data['term_id'],
                'url'   => (string) $page_data['url'],
                'kind'  => 'taxonomy',
            );
        }

        $page_id = (int) ( $page_data['page_id'] ?? 0 );

        return array(
            'label' => $label,
            'type'  => 'page',
            'id'    => $page_id,
            'url'   => $page_id > 0 ? (string) get_permalink( $page_id ) : '',
            'kind'  => 'post-type',
        );
    }

    /**
     * @param string $post_name
     *
     * @return bool
     */
    private function updateTemplatePart( string $post_name, int $menu_id ) : bool {
        $template_part_id = $this->findTemplatePart( $post_name );

        if( empty($template_part_id) ) {
            $template_part_id = wp_insert_post([
                'post_title' => ucfirst($post_name),
                'post_status' => 'publish',
                'post_type' => 'wp_template_part',
            ]);
        }

        $this->prepareTaxonomyAndTerms( $post_name, $template_part_id );

        $new_template_part = $this->loadTemplatePart($post_name);

        if(empty($new_template_part)) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Cant load template part: ' . $post_name );
            }
            return false;
        }

        $replaced_template_part = $this->replaceTemplatePart( $new_template_part, $menu_id );

        if(empty($replaced_template_part)) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Cant replace template part: ' . $post_name );
            }
            return false;
        }

        // serialize_blocks() escapes `--` as `\u002d\u002d`; wp_update_post() unslashes it back.
        return wp_update_post([
            'ID' => $template_part_id,
            'post_content' => wp_slash( $replaced_template_part )
        ]);
    }

    /**
     * @param string $new_template_part
     * @param int    $menu_id
     *
     * @return string
     */
    private function replaceTemplatePart( string $new_template_part, int $menu_id ): string {
        $blocks = parse_blocks( $new_template_part );

        foreach ($blocks as &$block) {
            $this->updateMenuRef($block, $menu_id);
        }

        return serialize_blocks($blocks);
    }

    /**
     * @param array $block
     * @param int   $menu_id
     *
     * @return void
     */
    private function updateMenuRef(array &$block, int $menu_id): void {
        if ($block['blockName'] === 'core/navigation') {
            $block['attrs']['ref'] = $menu_id;
        }

        if ( !empty( $block['innerBlocks'] ) ) {
            foreach ( $block['innerBlocks'] as &$innerBlock ) {
                $this->updateMenuRef( $innerBlock, $menu_id );
            }
        }
    }

    /**
     * Check if the website is a landing page type
     *
     * @return bool Whether the website is a landing page
     */
    private function isLandingPage() : bool {
        return WebsiteTypeHelper::has_website_type( 'landing page' );
    }

    /**
     * @param string $template_part
     *
     * @return string
     */
    private function loadTemplatePart( string $template_part ) : string {
        // Check if this is a footer template and apply landing page logic
        if ( $template_part === 'footer' && $this->isLandingPage() ) {
            $template_part = 'footer-landing';
        }

        $file = get_template_directory() . DIRECTORY_SEPARATOR . 'parts' . DIRECTORY_SEPARATOR . $template_part . '.html';

        if ( !file_exists( $file ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'file does not exist: ' . $file );
            }
            return '';
        }

        $file_content = file_get_contents( $file );

        if ($template_part === 'footer' || $template_part === 'footer-landing') {
            $file_content = $this->maybeRemoveWhatsappSocialLink($file_content);
        }

        if ($template_part === 'header') {
            $file_content = $this->maybeSwapCartForHostingerEcommerce($file_content);
        }

        $translator = new Translator();
        return $translator->translate_string( $file_content );
    }

    /**
     * Remove the WhatsApp social-link block from the footer when WhatsApp is not connected.
     *
     * @param string $content Template content
     * @return string Modified content
     */
    private function maybeRemoveWhatsappSocialLink( string $content ) : string {
        $contact = get_option( 'hostinger_ai_contact', array() );

        if ( ! empty( $contact['whatsapp'] ) ) {
            return $content;
        }

        return preg_replace(
            '/<!-- wp:social-link \{[^}]*"service":"whatsapp"[^}]*\} \/-->/',
            '',
            $content
        );
    }

    /**
     * Replace the WooCommerce mini-cart with the Hostinger Ecommerce cart icon (and drop
     * the Woo customer-account block) when the site is built through the ecommerce flow.
     *
     * @param string $content Template content
     * @return string Modified content
     */
    private function maybeSwapCartForHostingerEcommerce( string $content ) : string {
        if ( ! HostingerEcommerceHelper::is_active() ) {
            return $content;
        }

        $content = preg_replace( '/<!-- wp:woocommerce\/customer-account.*?\/-->/s', '', $content );

        $cart_block = '<!-- wp:shortcode -->[hostinger_ecommerce_cart_icon]<!-- /wp:shortcode -->';

        return preg_replace( '/<!-- wp:woocommerce\/mini-cart.*?\/-->/s', $cart_block, $content, 1 );
    }
    /**
     * @param string $post_name
     *
     * @return int
     */
    private function findTemplatePart( string $post_name ): int {
        $post_ids = get_posts(array
        (
            's'   => $post_name,
            'post_type'   => 'wp_template_part',
            'numberposts' => 1,
            'fields' => 'ids'
        ));

        if(empty($post_ids)) {
            return 0;
        }

        return array_shift( $post_ids );
    }

    /**
     * Attach the FSE taxonomies so the resolver treats this as the active
     * theme's header/footer part. Uses the WordPress term API (the raw $wpdb
     * inserts this replaced were the one wart in the legacy builder).
     *
     * @param string $post_name
     * @param int    $template_part_id
     *
     * @return void
     */
    private function prepareTaxonomyAndTerms( string $post_name, int $template_part_id ): void {
        wp_set_object_terms( $template_part_id, get_stylesheet(), 'wp_theme' );

        $area = ( 'footer' === $post_name ) ? 'footer' : 'header';
        wp_set_object_terms( $template_part_id, $area, 'wp_template_part_area' );
    }

}
