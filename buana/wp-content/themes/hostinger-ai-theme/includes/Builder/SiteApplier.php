<?php

namespace Hostinger\AiTheme\Builder;

use Closure;
use Elementor\Plugin as ElementorPlugin;
use Hostinger\AiTheme\Builder\Elementor\CssCache;
use Hostinger\AiTheme\Builder\Elementor\KitManager;
use Hostinger\AiTheme\Constants\GenerationConstant;
use Hostinger\AiTheme\Data\HostingerEcommerceHelper;
use Hostinger\AiTheme\Data\WebsiteTypeHelper;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use WP_Post;
use WP_Rewrite;
use WP_Term;
use WP_Theme_JSON;
use WP_Theme_JSON_Data;
use WP_Theme_JSON_Resolver;

defined( 'ABSPATH' ) || exit;

class SiteApplier {

    use ColorUtils;

    private AffiliateBuilder $affiliate_builder;

    private HostingerReachBuilder $reach_builder;

    private ?Closure $step_reporter = null;

    private const CONTACT_FIELD_CLASS = 'hostinger-ai-contact-field';

    private const CONTACT_GROUP_CLASS = 'hostinger-ai-contact-details';

    private const MAP_CLASS = 'hostinger-ai-map';

    private const HOSTINGER_ECOMMERCE_ENVELOPE_ID = 'hostinger-ecommerce-shop';

    private const PAGE_COLOR_ROLES = array(
        'page-background' => array(
            'sources'  => array( 'page-background', 'base', 'surface' ),
            'name'     => 'Page background',
            'fallback' => '#ffffff',
        ),
        'page-text'       => array(
            'sources'  => array( 'page-text', 'contrast', 'ink' ),
            'name'     => 'Page text',
            'fallback' => '#000000',
        ),
    );

    // Page-text is on-surface ink; using it on a dark primary makes buttons unreadable.
    private const BUTTON_TEXT_ROLE = array(
        'sources'  => array( 'button-text', 'ink-inverse', 'light' ),
        'name'     => 'Button text',
        'fallback' => '#ffffff',
    );

    public function __construct(
        ?AffiliateBuilder $affiliate_builder = null,
        ?HostingerReachBuilder $reach_builder = null
    ) {
        $this->affiliate_builder = $affiliate_builder ?? new AffiliateBuilder();
        $this->reach_builder     = $reach_builder ?? new HostingerReachBuilder();
    }

    public function init(): void {
        add_filter( 'wp_theme_json_data_theme', array( $this, 'merge_theme_json' ), 1000 );
    }

    public function merge_theme_json( WP_Theme_JSON_Data $theme_json ): WP_Theme_JSON_Data {
        if ( ! BundleSchema::is_active() ) {
            return $theme_json;
        }

        $delta = get_option( BundleSchema::OPTION_THEME_JSON, array() );

        if ( empty( $delta ) || ! is_array( $delta ) ) {
            return $theme_json;
        }

        return $theme_json->update_with( $this->with_page_color_presets( $delta ) );
    }

    // WP replaces the whole palette; envelope uses base/contrast, so page-background / page-text would vanish.
    private function with_page_color_presets( array $delta ): array {
        $palette = $delta['settings']['color']['palette'] ?? null;

        if ( ! is_array( $palette ) || empty( $palette ) ) {
            return $delta;
        }

        $page_colors = $this->get_page_colors_from_palette( $this->get_colors_by_slug( $palette ) );

        $delta['settings']['color']['palette'] = $this->merge_page_colors( $palette, $page_colors );

        return $delta;
    }

    private function get_colors_by_slug( array $palette ): array {
        $colors = array();

        foreach ( $palette as $entry ) {
            if ( is_array( $entry ) && isset( $entry['slug'], $entry['color'] ) ) {
                $colors[ (string) $entry['slug'] ] = (string) $entry['color'];
            }
        }

        return $colors;
    }

    private function get_page_colors_from_palette( array $colors ): array {
        $page_colors = array();

        foreach ( self::PAGE_COLOR_ROLES as $slug => $role ) {
            $page_colors[ $slug ] = $this->first_valid_color( $colors, $role['sources'], $role['fallback'] );
        }

        $page_colors['page-text'] = $this->readable_against( $page_colors['page-background'], $page_colors['page-text'] );

        $primary = $this->first_valid_color( $colors, array( 'primary', 'color3' ), '#000000' );

        $page_colors['button-text'] = $this->readable_against(
            $primary,
            $this->first_valid_color(
                $colors,
                self::BUTTON_TEXT_ROLE['sources'],
                self::BUTTON_TEXT_ROLE['fallback']
            )
        );

        return $page_colors;
    }

    private function merge_page_colors( array $palette, array $page_colors ): array {
        $managed_slugs = array_merge( array_keys( self::PAGE_COLOR_ROLES ), array( 'button-text' ) );
        $merged        = array();

        foreach ( $palette as $entry ) {
            if ( ! is_array( $entry ) || ! in_array( $entry['slug'] ?? '', $managed_slugs, true ) ) {
                $merged[] = $entry;
            }
        }

        foreach ( self::PAGE_COLOR_ROLES as $slug => $role ) {
            $merged[] = array(
                'slug'  => $slug,
                'color' => $page_colors[ $slug ],
                'name'  => $role['name'],
            );
        }

        $merged[] = array(
            'slug'  => 'button-text',
            'color' => $page_colors['button-text'],
            'name'  => self::BUTTON_TEXT_ROLE['name'],
        );

        return $merged;
    }

    private function first_valid_color( array $colors, array $sources, string $fallback ): string {
        foreach ( $sources as $slug ) {
            $color = $colors[ $slug ] ?? '';
            if ( $color !== '' && $this->is_valid_hex_color( $color ) ) {
                return $color;
            }
        }

        return $fallback;
    }

    private function readable_against( string $background, string $text ): string {
        if ( $this->calculate_contrast_ratio( $background, $text ) >= $this->get_required_contrast_ratio() ) {
            return $text;
        }

        $on_black = $this->calculate_contrast_ratio( $background, '#000000' );
        $on_white = $this->calculate_contrast_ratio( $background, '#ffffff' );

        return $on_black >= $on_white ? '#000000' : '#ffffff';
    }

    /**
     * @throws InvalidArgumentException When the envelope is unsupported/malformed (HTTP 422).
     * @throws RuntimeException         When every page fails to apply (HTTP 500).
     */
    public function apply( array $envelope, ?callable $on_step = null ): array {
        $this->step_reporter = $on_step === null ? null : Closure::fromCallable( $on_step );

        try {
            return $this->apply_envelope( $envelope );
        } finally {
            $this->step_reporter = null;
        }
    }

    private function apply_envelope( array $envelope ): array {
        if ( ! BundleSchema::validate( $envelope ) ) {
            throw new InvalidArgumentException( 'Unsupported or malformed website envelope.' );
        }

        $editor = (string) $envelope['editor'];
        $this->guard_editor( $editor );

        $this->report_step( 'prepare_site' );

        $warnings      = array();
        $customization = $this->prepare_site( $envelope, $editor );

        $this->report_step( 'reach_signups' );

        $envelope = $this->prepare_reach_signups( $envelope, $editor, $warnings );
        $envelope = $this->prepare_contact_details( $envelope, $editor );

        $this->report_step( 'sideload_media' );

        $media      = new MediaSideloader();
        $sideloaded = $media->sideload(
            $this->collect_all_urls( $envelope, $editor, $media ),
            fn() => $this->report_step( 'sideload_media' )
        );
        $media_map  = $sideloaded['map'];
        $warnings   = array_merge( $warnings, $sideloaded['warnings'] );

        $pages    = $this->create_pages(
            $envelope,
            $editor,
            $media,
            $media_map,
            $this->is_booking_envelope( $envelope )
        );
        $warnings = array_merge( $warnings, $pages['warnings'] );

        if ( empty( $pages['post_ids'] ) ) {
            throw new RuntimeException( 'Every page failed to apply.' );
        }

        // Track ids immediately: clean-before-apply is destructive.
        update_option( BundleSchema::OPTION_PAGES, $pages['post_ids'] );
        $this->record_created_pages( $envelope['pages'], $pages['id_map'] );

        if ( $editor === 'elementor' ) {
            $this->polish_elementor_kit( $customization );
        }

        $this->report_step( 'woocommerce' );

        $products_created = $this->apply_woocommerce( $envelope['woocommerce'] ?? null, $media_map, $warnings );
        $this->ensure_woocommerce_store_pages();

        if ( HostingerEcommerceHelper::is_active() ) {
            $this->ensure_hostinger_ecommerce_shop_page( $envelope, $editor, $pages );
        }

        $this->report_step( 'menu' );

        $menu_id = $this->build_menu_safely( $envelope, $pages, $warnings );

        $this->report_step( 'brand' );

        $logo_url = $this->apply_brand(
            is_array( $envelope['brand'] ?? null ) ? $envelope['brand'] : array(),
            $media_map
        );
        if ( $logo_url !== '' ) {
            $customization['logo_url'] = $logo_url;
        }

        $this->report_step( 'blog' );

        $posts_created = $this->apply_blog( $envelope['blog'] ?? null, $media, $media_map );

        if ( $menu_id > 0 ) {
            update_option( BundleSchema::OPTION_MENU, $menu_id );
        }

        $this->report_step( 'finish' );

        $this->finish_apply( $editor, $pages['post_ids'] );

        GenerationState::complete();

        return array(
            'pages_created'     => count( $pages['post_ids'] ),
            'front_page_id'     => $pages['front_id'],
            'menu_id'           => $menu_id,
            'products_created'  => $products_created,
            'posts_created'     => $posts_created,
            'assets_sideloaded' => count( $media_map ),
            'customization'     => $customization,
            'warnings'          => array_values( $warnings ),
        );
    }

    private function report_step( string $step ): void {
        if ( $this->step_reporter !== null ) {
            ( $this->step_reporter )( $step );
        }
    }

    private function prepare_site( array $envelope, string $editor ): array {
        $this->clean_before_apply();
        $this->set_mode( $editor );
        $this->apply_language( $envelope['language'] ?? array() );
        $this->apply_single_page_chrome( ! empty( $envelope['single_page'] ) );

        return $this->apply_theme_globals(
            is_array( $envelope['theme'] ?? null ) ? $envelope['theme'] : array(),
            $editor
        );
    }

    private function polish_elementor_kit( array $customization ): void {
        EnvelopeCustomization::update_elementor_heading_fonts(
            (string) ( $customization['font_pair']['heading']['font_family'] ?? '' )
        );

        $this->apply_elementor_kit_polish();
    }

    private function build_menu_safely( array $envelope, array $pages, array &$warnings ): int {
        try {
            return $this->build_menu( $envelope['menu'] ?? array(), $pages['id_map'], $pages['post_ids'] );
        } catch ( Throwable ) {
            $warnings[] = 'Navigation menu failed to apply.';

            return 0;
        }
    }

    private function finish_apply( string $editor, array $post_ids ): void {
        $this->ensure_pretty_permalinks();
        $this->bust_caches( $editor, $post_ids );

        $this->report_step( 'install_language' );

        $this->install_selected_language();
    }

    private function guard_editor( string $editor ): void {
        if ( $editor === 'elementor' && ! Helper::is_elementor_active() ) {
            throw new InvalidArgumentException( 'Elementor is required for this website but is not active.' );
        }
    }

    // Enable Reach's Elementor widget, or drop the signup if it still cannot render.
    private function prepare_reach_signups( array $envelope, string $editor, array &$warnings ): array {
        $this->reach_builder->generate_form();

        if ( $editor !== 'elementor' ) {
            return $envelope;
        }

        $this->reach_builder->activate_elementor_integration();

        if ( $this->reach_builder->can_render_elementor_form() ) {
            return $envelope;
        }

        $removed = 0;

        foreach ( $envelope['pages'] as $index => $page ) {
            $content = is_array( $page['content'] ?? null ) ? $page['content'] : array();
            $kept    = array_values(
                array_filter( $content, fn( $node ) => ! $this->contains_reach_widget( $node ) )
            );

            if ( count( $kept ) === count( $content ) || $kept === array() ) {
                continue;
            }

            $removed += count( $content ) - count( $kept );

            $envelope['pages'][ $index ]['content'] = $kept;
        }

        if ( $removed > 0 ) {
            $warnings[] = 'Newsletter signup skipped: Hostinger Reach is not active on this site.';
        }

        return $envelope;
    }

    private function contains_reach_widget( $node ): bool {
        if ( ! is_array( $node ) ) {
            return false;
        }

        if ( ( $node['widgetType'] ?? '' ) === HostingerReachBuilder::PLUGIN_SLUG ) {
            return true;
        }

        foreach ( $node['elements'] ?? array() as $child ) {
            if ( $this->contains_reach_widget( $child ) ) {
                return true;
            }
        }

        return false;
    }

    // Substitute owner contact details; drop rows and the map the owner never supplied.
    private function prepare_contact_details( array $envelope, string $editor ): array {
        $values = $this->contact_display_values();

        $context = array(
            'tokens'      => $this->contact_token_replacements( $values ),
            'drop_fields' => array_keys( array_filter( $values, fn( $value ) => $value === '' ) ),
            'drop_map'    => $values['address'] === '',
        );

        foreach ( $envelope['pages'] as $index => $page ) {
            if ( ! is_array( $page ) || ! is_array( $page['content'] ?? null ) ) {
                continue;
            }

            $envelope['pages'][ $index ]['content'] = $this->apply_contact_to_nodes(
                $page['content'],
                $editor,
                $context
            );
        }

        return $envelope;
    }

    private function contact_display_values(): array {
        $contact = get_option( 'hostinger_ai_contact', array() );

        if ( ! is_array( $contact ) ) {
            $contact = array();
        }

        $country_code = ltrim( (string) ( $contact['phone_country_code'] ?? '' ), '+' );
        $phone        = trim( (string) ( $contact['phone'] ?? '' ) );

        return array(
            'email'   => trim( (string) ( $contact['email'] ?? '' ) ),
            'phone'   => $phone === '' ? '' : '(+' . $country_code . ') ' . $phone,
            'address' => trim( (string) ( $contact['address'] ?? '' ) ),
        );
    }

    private function contact_token_replacements( array $values ): array {
        $dialable = preg_replace( '/[^0-9+]/', '', $values['phone'] ?? '' );

        return array(
            'trans-contact_email'   => esc_html( $values['email'] ),
            'trans-contact_phone'   => esc_html( $values['phone'] ),
            'trans-contact_address' => esc_html( $values['address'] ),
            'trans-encoded_email'   => esc_attr( $values['email'] ),
            'trans-encoded_phone'   => esc_attr( (string) $dialable ),
            'trans-encoded_address' => rawurlencode( $values['address'] ),
        );
    }

    private function apply_contact_to_nodes( array $nodes, string $editor, array $context ): array {
        $kept = array();

        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                $kept[] = $node;
                continue;
            }

            if ( $this->contact_node_is_dropped( $node, $editor, $context ) ) {
                continue;
            }

            $processed = $this->apply_contact_to_node( $node, $editor, $context );

            if ( $this->contact_wrapper_is_emptied( $processed, $editor ) ) {
                continue;
            }

            $kept[] = $processed;
        }

        return array_values( $kept );
    }

    private function apply_contact_to_node( array $node, string $editor, array $context ): array {
        if ( $editor === 'elementor' ) {
            if ( is_array( $node['settings'] ?? null ) ) {
                $node['settings'] = $this->substitute_contact_tokens( $node['settings'], $context['tokens'] );
            }

            if ( is_array( $node['elements'] ?? null ) ) {
                $node['elements'] = $this->apply_contact_to_nodes( $node['elements'], $editor, $context );
            }

            return $node;
        }

        $children = is_array( $node['innerBlocks'] ?? null ) ? $node['innerBlocks'] : array();

        if ( $children !== array() ) {
            $survives = array();
            $kept     = array();

            foreach ( $children as $child ) {
                $dropped   = is_array( $child ) && $this->contact_node_is_dropped( $child, $editor, $context );
                $processed = $child;

                if ( ! $dropped && is_array( $child ) ) {
                    $processed = $this->apply_contact_to_node( $child, $editor, $context );
                    $dropped   = $this->contact_wrapper_is_emptied( $processed, $editor );
                }

                $survives[] = ! $dropped;

                if ( ! $dropped ) {
                    $kept[] = $processed;
                }
            }

            // serialize_blocks() pairs each child with a null in innerContent.
            if ( in_array( false, $survives, true ) ) {
                $node['innerContent'] = $this->prune_inner_content(
                    is_array( $node['innerContent'] ?? null ) ? $node['innerContent'] : array(),
                    $survives
                );
            }

            $node['innerBlocks'] = array_values( $kept );
        }

        foreach ( array( 'attrs', 'innerHTML', 'innerContent' ) as $key ) {
            if ( array_key_exists( $key, $node ) ) {
                $node[ $key ] = $this->substitute_contact_tokens( $node[ $key ], $context['tokens'] );
            }
        }

        return $node;
    }

    private function contact_wrapper_is_emptied( array $node, string $editor ): bool {
        if ( $editor === 'elementor' ) {
            $classes = (string) ( $node['settings']['_css_classes'] ?? '' );

            return str_contains( $classes, self::CONTACT_GROUP_CLASS )
                && ( $node['elements'] ?? array() ) === array();
        }

        $classes = (string) ( $node['attrs']['className'] ?? '' );

        return str_contains( $classes, self::CONTACT_GROUP_CLASS )
            && ( $node['innerBlocks'] ?? array() ) === array();
    }

    private function prune_inner_content( array $inner_content, array $survives ): array {
        $child = 0;
        $kept  = array();

        foreach ( $inner_content as $chunk ) {
            if ( $chunk !== null ) {
                $kept[] = $chunk;
                continue;
            }

            if ( $survives[ $child ] ?? true ) {
                $kept[] = null;
            }

            ++$child;
        }

        return array_values( $kept );
    }

    private function contact_node_is_dropped( array $node, string $editor, array $context ): bool {
        $markup = $editor === 'elementor'
            ? (string) ( $node['settings']['editor'] ?? '' ) . (string) ( $node['settings']['html'] ?? '' )
            : (string) ( $node['innerHTML'] ?? '' );

        if ( $markup === '' ) {
            return false;
        }

        if ( $context['drop_map'] && str_contains( $markup, self::MAP_CLASS ) ) {
            return true;
        }

        foreach ( $context['drop_fields'] as $field ) {
            if ( str_contains( $markup, self::CONTACT_FIELD_CLASS . '--' . $field ) ) {
                return true;
            }
        }

        return false;
    }

    private function substitute_contact_tokens( $value, array $tokens ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $item ) {
                $value[ $key ] = $this->substitute_contact_tokens( $item, $tokens );
            }

            return $value;
        }

        if ( is_string( $value ) ) {
            return strtr( $value, $tokens );
        }

        return $value;
    }

    private function clean_before_apply(): void {
        $pages = get_posts(
            array(
                'post_type'   => 'page',
                'post_status' => 'any',
                'numberposts' => -1,
                'fields'      => 'ids',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_key'    => '_hostinger_ai_generated',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                'meta_value'  => '1',
            )
        );

        foreach ( $pages as $page_id ) {
            wp_delete_post( (int) $page_id, true );
        }

        $menu_id = (int) get_option( BundleSchema::OPTION_MENU, 0 );
        if ( $menu_id > 0 ) {
            wp_delete_post( $menu_id, true );
        }

        $this->delete_previous_products();
        $this->affiliate_builder->clear_catalog();
        ( new ProductCategoryManager() )->clear_created_categories();

        foreach ( (array) get_option( BundleSchema::OPTION_BLOG_POSTS, array() ) as $post_id ) {
            wp_delete_post( (int) $post_id, true );
        }
        foreach ( (array) get_option( BundleSchema::OPTION_BLOG_CATS, array() ) as $term_id ) {
            wp_delete_term( (int) $term_id, 'category' );
        }

        $this->remove_sample_hello_world_post();

        remove_theme_mod( 'custom_logo' );
        LogoAdapter::discard_variants();

        delete_option( BundleSchema::OPTION_PAGES );
        delete_option( BundleSchema::OPTION_MENU );
        delete_option( BundleSchema::OPTION_PRODUCTS );
        delete_option( BundleSchema::OPTION_BLOG_POSTS );
        delete_option( BundleSchema::OPTION_BLOG_CATS );
        delete_option( BundleSchema::OPTION_ORIGINAL_LOGO );
        delete_option( BundleSchema::OPTION_CREATED_PAGES );

        $this->reset_user_global_styles();
    }

    private function remove_sample_hello_world_post(): void {
        $hello_world = get_page_by_path( 'hello-world', OBJECT, 'post' );
        if ( ! $hello_world instanceof WP_Post ) {
            return;
        }

        wp_delete_post( (int) $hello_world->ID, true );
    }

    // Force-delete stamped products, including trash (`any` does not cover it).
    private function delete_previous_products(): void {
        $tracked = array_map( 'intval', (array) get_option( BundleSchema::OPTION_PRODUCTS, array() ) );

        $stamped = get_posts(
            array(
                'post_type'   => 'product',
                'post_status' => array( 'any', 'trash' ),
                'numberposts' => -1,
                'fields'      => 'ids',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_key'    => '_hostinger_ai_generated',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                'meta_value'  => '1',
            )
        );

        $product_ids = array_unique( array_merge( $tracked, array_map( 'intval', $stamped ) ) );

        foreach ( $product_ids as $product_id ) {
            if ( $product_id > 0 ) {
                wp_delete_post( $product_id, true );
            }
        }
    }

    // User Global Styles win over the theme layer and would keep a stale palette.
    private function reset_user_global_styles(): void {
        if ( ! class_exists( WP_Theme_JSON_Resolver::class ) ) {
            return;
        }

        $post_id = (int) WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
        if ( $post_id <= 0 ) {
            return;
        }

        $empty = wp_json_encode(
            array(
                'version'                     => WP_Theme_JSON::LATEST_SCHEMA,
                'isGlobalStylesUserThemeJSON' => true,
            )
        );

        wp_update_post(
            array(
                'ID'           => $post_id,
                'post_content' => wp_slash( (string) $empty ),
            )
        );

        WP_Theme_JSON_Resolver::clean_cached_data();
    }

    private function set_mode( string $editor ): void {
        update_option( BundleSchema::OPTION_MODE, BundleSchema::MODE_ENVELOPE );
        update_option( 'hostinger_ai_builder_type', $editor );
    }

    // Envelope language is often `en`; WordPress needs a locale like `en_US`.
    private function apply_language( $language ): void {
        if ( ! is_array( $language ) ) {
            return;
        }

        $code = $language['code'] ?? '';
        if ( ! is_string( $code ) || $code === '' ) {
            return;
        }

        $code   = sanitize_text_field( $code );
        $stored = (string) get_option( 'hostinger_ai_selected_language', '' );

        if ( $this->is_installable_locale( $stored ) && ! $this->is_installable_locale( $code ) ) {
            return;
        }

        update_option( 'hostinger_ai_selected_language', $code );
    }

    // `en` is a language tag, not a WP locale.
    private function is_installable_locale( string $locale ): bool {
        return $locale === 'en_US' || str_contains( $locale, '_' );
    }

    private function install_selected_language(): void {
        $locale = (string) get_option( 'hostinger_ai_selected_language', '' );

        if ( ! $this->is_installable_locale( $locale ) ) {
            return;
        }

        Helper::install_and_set_language( $locale );
    }

    private function apply_single_page_chrome( bool $single_page ): void {
        $options = get_option( 'hostinger_ai_theme_display_options', array() );
        if ( ! is_array( $options ) ) {
            $options = array();
        }

        if ( $single_page ) {
            $options['hide_header'] = 1;
            update_option( 'hostinger_ai_theme_display_options', $options );
            return;
        }

        unset( $options['hide_header'] );
        update_option( 'hostinger_ai_theme_display_options', $options );
    }

    private function apply_theme_globals( array $theme, string $editor ): array {
        return EnvelopeCustomization::capture( $theme, $editor );
    }

    private function collect_all_urls( array $envelope, string $editor, MediaSideloader $media ): array {
        $urls = array();

        foreach ( $envelope['pages'] as $page ) {
            $content = $page['content'] ?? array();
            if ( ! is_array( $content ) ) {
                continue;
            }
            $urls = array_merge(
                $urls,
                $editor === 'elementor'
                    ? $media->collect_elementor_urls( $content )
                    : $media->collect_gutenberg_urls( $content )
            );
        }

        $logo = $envelope['brand']['logo']['signed_url'] ?? '';
        if ( is_string( $logo ) && $logo !== '' ) {
            $urls[] = $logo;
        }

        foreach ( $envelope['woocommerce']['products'] ?? array() as $product ) {
            foreach ( $product['images'] ?? array() as $image ) {
                if ( ! empty( $image['src'] ) && is_string( $image['src'] ) ) {
                    $urls[] = $image['src'];
                }
            }
        }

        foreach ( $envelope['blog']['posts'] ?? array() as $post ) {
            $featured = $post['featured_image']['src'] ?? '';
            if ( is_string( $featured ) && $featured !== '' ) {
                $urls[] = $featured;
            }
            if ( is_array( $post['content'] ?? null ) ) {
                $urls = array_merge( $urls, $media->collect_gutenberg_urls( $post['content'] ) );
            }
        }

        return array_values( array_unique( $urls ) );
    }

    private function create_pages(
        array $envelope,
        string $editor,
        MediaSideloader $media,
        array $media_map,
        bool $is_booking
    ): array {
        $post_ids     = array();
        $id_map       = array();
        $warnings     = array();
        $home_page_id = (string) ( $envelope['home_page_id'] ?? '' );
        $seo          = new Seo();

        $explicit_front = 0;
        $home_candidate = 0;

        foreach ( $envelope['pages'] as $page ) {
            if ( ! is_array( $page ) ) {
                continue;
            }

            $this->report_step( 'create_pages' );

            try {
                $post_id = $this->create_page( $page, $editor, $media, $media_map, $is_booking );
            } catch ( Throwable $e ) {
                $post_id = 0;
            }

            if ( $post_id <= 0 ) {
                $warnings[] = sprintf(
                    'Page failed to apply: %s',
                    (string) ( $page['title'] ?? ( $page['id'] ?? 'unknown' ) )
                );
                continue;
            }

            $post_ids[] = $post_id;
            $this->apply_page_seo( $seo, $post_id, $page, $envelope );
            $envelope_id = (string) ( $page['id'] ?? '' );
            if ( $envelope_id !== '' ) {
                $id_map[ $envelope_id ] = $post_id;
                if ( $envelope_id === $home_page_id ) {
                    $explicit_front = $post_id;
                }
            }
            if ( ! empty( $page['is_home'] ) && $home_candidate === 0 ) {
                $home_candidate = $post_id;
            }
        }

        $front_id = $explicit_front;
        if ( $front_id === 0 ) {
            $front_id = $home_candidate !== 0 ? $home_candidate : ( $post_ids[0] ?? 0 );
        }

        if ( $front_id > 0 ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', $front_id );
        }

        return array(
            'post_ids' => $post_ids,
            'id_map'   => $id_map,
            'front_id' => $front_id,
            'warnings' => $warnings,
        );
    }

    // Restore still reads the legacy created pages map.
    private function record_created_pages( array $pages, array $id_map ): void {
        $created = array();

        foreach ( $pages as $page ) {
            if ( ! is_array( $page ) ) {
                continue;
            }

            $envelope_id = (string) ( $page['id'] ?? '' );
            $post_id     = $envelope_id !== '' ? (int) ( $id_map[ $envelope_id ] ?? 0 ) : 0;

            if ( $post_id <= 0 ) {
                continue;
            }

            $key = $envelope_id !== '' ? sanitize_key( $envelope_id ) : (string) $post_id;

            $created[ $key ] = array(
                'title'   => (string) ( $page['title'] ?? get_the_title( $post_id ) ),
                'page_id' => $post_id,
            );
        }

        update_option( BundleSchema::OPTION_CREATED_PAGES, $created );
    }

    private function record_created_page( string $key, string $title, int $page_id ): void {
        if ( $key === '' || $page_id <= 0 ) {
            return;
        }

        $created = get_option( BundleSchema::OPTION_CREATED_PAGES, array() );

        if ( ! is_array( $created ) ) {
            $created = array();
        }

        $created[ $key ] = array(
            'title'   => $title,
            'page_id' => $page_id,
        );

        update_option( BundleSchema::OPTION_CREATED_PAGES, $created );
    }

    private function create_page(
        array $page,
        string $editor,
        MediaSideloader $media,
        array $media_map,
        bool $is_booking
    ): int {
        $title            = (string) ( $page['title'] ?? '' );
        $slug             = (string) ( $page['slug'] ?? '' );
        $content          = is_array( $page['content'] ?? null ) ? $page['content'] : array();
        $add_booking_form = $is_booking && $this->contains_booking_catalog_shortcode( $content, $editor );

        $postarr = array(
            'post_title'   => sanitize_text_field( $title !== '' ? $title : 'Page' ),
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        );
        if ( $slug !== '' ) {
            $postarr['post_name'] = sanitize_title( $slug );
        }

        if ( $editor === 'elementor' ) {
            $rewritten = $media->rewrite_elementor( $content, $media_map );
            $rewritten = EnvelopeCustomization::bind_elementor_globals( $rewritten );

            if ( $add_booking_form ) {
                $rewritten[] = $this->get_elementor_booking_form( $page );
            }

            if ( HostingerEcommerceHelper::is_active() ) {
                $rewritten = $this->rewrite_products_shortcodes_with_hostinger_ecommerce( $rewritten, $editor );
            }

            $rewritten = $this->rewrite_affiliate_shortcodes( $rewritten, $editor, sanitize_text_field( $title ) );

            $elementor_json = wp_slash(
                (string) wp_json_encode( $rewritten, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
            );

            $page_id = Helper::insert_trusted_post( $postarr );
            if ( is_wp_error( $page_id ) || ! $page_id ) {
                return 0;
            }
            $page_id = (int) $page_id;

            update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
            update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
            update_post_meta( $page_id, '_elementor_version', Helper::get_elementor_version() );
            update_post_meta( $page_id, '_elementor_data', $elementor_json );
            update_post_meta( $page_id, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );
        } else {
            $rewritten = $media->rewrite_gutenberg( $content, $media_map );

            if ( $add_booking_form ) {
                $rewritten = $this->append_gutenberg_booking_form( $rewritten );
            }

            if ( HostingerEcommerceHelper::is_active() ) {
                $rewritten = $this->rewrite_products_shortcodes_with_hostinger_ecommerce( $rewritten, $editor );
            }

            $rewritten = $this->rewrite_affiliate_shortcodes( $rewritten, $editor, sanitize_text_field( $title ) );

            $postarr['post_content'] = serialize_blocks( $rewritten );

            $page_id = Helper::insert_trusted_post( $postarr );
            if ( is_wp_error( $page_id ) || ! $page_id ) {
                return 0;
            }
            $page_id = (int) $page_id;
        }

        update_post_meta( $page_id, '_wp_page_template', 'no-title' );
        update_post_meta( $page_id, '_hostinger_ai_generated', '1' );

        if ( $slug !== '' && sanitize_title( $slug ) === 'shop' && get_option( 'hostinger_ai_woo', false ) ) {
            update_option( 'woocommerce_shop_page_id', $page_id );
        }

        return $page_id;
    }

    // Envelope pages have no seo section; without this, og/meta tags are empty.
    private function apply_page_seo( Seo $seo, int $page_id, array $page, array $envelope ): void {
        $title = trim( (string) ( $page['title'] ?? '' ) );
        if ( $title !== '' ) {
            $seo->load_seo_title( $page_id, $title );
        }

        $description = trim( (string) ( $page['excerpt'] ?? '' ) );
        if ( $description === '' ) {
            $brand       = is_array( $envelope['brand'] ?? null ) ? $envelope['brand'] : array();
            $description = trim( (string) ( $brand['tagline'] ?? '' ) );
        }
        if ( $description !== '' ) {
            $seo->load_seo_description( $page_id, $description );
        }

        $seo->add_seo_meta_tags( $page_id );
    }

    // Kit save() no-ops when Elementor has not created a kit yet.
    private function apply_elementor_kit_polish(): void {
        $kit = new KitManager();
        $kit->set_global_image_border_radius( 8 );
        $kit->set_global_container_mobile_layout_padding( 1 );
    }

    private function is_booking_envelope( array $envelope ): bool {
        $woocommerce  = is_array( $envelope['woocommerce'] ?? null ) ? $envelope['woocommerce'] : array();
        $catalog_kind = $woocommerce['catalog_kind'] ?? '';
        $catalog_kind = is_string( $catalog_kind ) ? sanitize_key( $catalog_kind ) : '';

        if ( $catalog_kind === 'service' ) {
            return true;
        }

        foreach ( $woocommerce['products'] ?? array() as $product ) {
            if ( is_array( $product ) && ( $product['catalog_kind'] ?? '' ) === 'service' ) {
                return true;
            }
        }

        return WebsiteTypeHelper::has_website_type( 'booking' );
    }

    private function rewrite_products_shortcodes_with_hostinger_ecommerce( array $nodes, string $editor ): array {
        foreach ( $nodes as $index => $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }

            if ( $editor === 'elementor' && ( $node['widgetType'] ?? '' ) === 'shortcode' ) {
                $shortcode = $node['settings']['shortcode'] ?? '';
                if ( is_string( $shortcode ) ) {
                    $node['settings']['shortcode'] = $this->replace_products_shortcode_with_hostinger_ecommerce( $shortcode );
                }
            }

            if ( $editor !== 'elementor' && ( $node['blockName'] ?? '' ) === 'core/shortcode' ) {
                if ( isset( $node['innerHTML'] ) && is_string( $node['innerHTML'] ) ) {
                    $node['innerHTML'] = $this->replace_products_shortcode_with_hostinger_ecommerce( $node['innerHTML'] );
                }

                if ( isset( $node['innerContent'] ) && is_array( $node['innerContent'] ) ) {
                    $node['innerContent'] = array_map(
                        fn( $chunk ) => is_string( $chunk ) ? $this->replace_products_shortcode_with_hostinger_ecommerce( $chunk ) : $chunk,
                        $node['innerContent']
                    );
                }
            }

            foreach ( array( 'elements', 'innerBlocks' ) as $children_key ) {
                if ( isset( $node[ $children_key ] ) && is_array( $node[ $children_key ] ) ) {
                    $node[ $children_key ] = $this->rewrite_products_shortcodes_with_hostinger_ecommerce( $node[ $children_key ], $editor );
                }
            }

            $nodes[ $index ] = $node;
        }

        return $nodes;
    }

    private function replace_products_shortcode_with_hostinger_ecommerce( string $shortcode ): string {
        return (string) preg_replace(
            '/\[products\b[^\]]*\]/i',
            HostingerEcommerceBuilder::PRODUCTS_SHORTCODE,
            $shortcode
        );
    }

    private function rewrite_affiliate_shortcodes( array $nodes, string $editor, string $fallback_keyword ): array {
        $result = array();

        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                $result[] = $node;
                continue;
            }

            $keyword = $this->affiliate_marker_keyword( $node, $editor );

            if ( $keyword !== null ) {
                foreach ( $this->affiliate_replacement_nodes( $keyword, $editor, $fallback_keyword ) as $replacement ) {
                    $result[] = $replacement;
                }

                continue;
            }

            $result[] = $this->rewrite_affiliate_children( $node, $editor, $fallback_keyword );
        }

        return $result;
    }

    private function rewrite_affiliate_children( array $node, string $editor, string $fallback_keyword ): array {
        if ( $editor === 'elementor' ) {
            if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
                $node['elements'] = $this->rewrite_affiliate_shortcodes( $node['elements'], $editor, $fallback_keyword );
            }

            return $node;
        }

        if ( ! isset( $node['innerBlocks'] ) || ! is_array( $node['innerBlocks'] ) || $node['innerBlocks'] === array() ) {
            return $node;
        }

        $survives = array();
        $kept     = array();

        foreach ( $node['innerBlocks'] as $child ) {
            $keyword = is_array( $child ) ? $this->affiliate_marker_keyword( $child, $editor ) : null;

            if ( $keyword !== null ) {
                $replacements = $this->affiliate_replacement_nodes( $keyword, $editor, $fallback_keyword );

                foreach ( $replacements as $replacement ) {
                    $kept[] = $replacement;
                }

                $survives[] = $replacements !== array();

                continue;
            }

            $survives[] = true;
            $kept[]     = is_array( $child ) ? $this->rewrite_affiliate_children( $child, $editor, $fallback_keyword ) : $child;
        }

        if ( in_array( false, $survives, true ) ) {
            $node['innerContent'] = $this->prune_inner_content(
                is_array( $node['innerContent'] ?? null ) ? $node['innerContent'] : array(),
                $survives
            );
        }

        $node['innerBlocks'] = array_values( $kept );

        return $node;
    }

    private function affiliate_replacement_nodes( string $keyword, string $editor, string $fallback_keyword ): array {
        if ( $keyword === '' ) {
            $keyword = $fallback_keyword;
        }

        if ( $keyword === '' ) {
            return array();
        }

        if ( $editor === 'elementor' ) {
            $widget = $this->affiliate_builder->generate_elementor_widget( $keyword );

            return $widget === array() ? array() : array( $widget );
        }

        $blocks = array();

        foreach ( parse_blocks( $this->affiliate_builder->generate_shortcode( $keyword ) ) as $block ) {
            if ( ! empty( $block['blockName'] ) ) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    private function affiliate_marker_keyword( array $node, string $editor ): ?string {
        $shortcode = '';

        if ( $editor === 'elementor' && ( $node['widgetType'] ?? '' ) === 'shortcode' ) {
            $shortcode = $node['settings']['shortcode'] ?? '';
        }

        if ( $editor !== 'elementor' && ( $node['blockName'] ?? '' ) === 'core/shortcode' ) {
            $shortcode = $node['innerHTML'] ?? '';

            if ( ( ! is_string( $shortcode ) || $shortcode === '' ) && isset( $node['innerContent'] ) && is_array( $node['innerContent'] ) ) {
                $shortcode = implode( '', array_filter( $node['innerContent'], 'is_string' ) );
            }
        }

        if ( ! is_string( $shortcode ) || ! preg_match( '/\[hostinger_affiliate\b[^\]]*\]/i', $shortcode, $matches ) ) {
            return null;
        }

        $atts    = shortcode_parse_atts( trim( $matches[0], '[]' ) );
        $keyword = is_array( $atts ) && isset( $atts['keyword'] ) ? (string) $atts['keyword'] : '';

        return sanitize_text_field( $keyword );
    }

    private function contains_booking_catalog_shortcode( array $nodes, string $editor ): bool {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }

            $shortcode = '';

            if ( $editor === 'elementor' && ( $node['widgetType'] ?? '' ) === 'shortcode' ) {
                $shortcode = $node['settings']['shortcode'] ?? '';
            }

            if ( $editor !== 'elementor' && ( $node['blockName'] ?? '' ) === 'core/shortcode' ) {
                $shortcode = $node['innerHTML'] ?? '';
            }

            if ( is_string( $shortcode )
                && str_contains( $shortcode, '[products' )
                && str_contains( $shortcode, 'hostinger-ai-services' )
                && ! preg_match( '/\blimit\s*=\s*["\']1["\']/i', $shortcode )
            ) {
                return true;
            }

            foreach ( array( 'elements', 'innerBlocks' ) as $children_key ) {
                $children = $node[ $children_key ] ?? array();

                if ( is_array( $children ) && $this->contains_booking_catalog_shortcode( $children, $editor ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function append_gutenberg_booking_form( array $blocks ): array {
        $booking_blocks = parse_blocks( '<!-- wp:hostinger-ai-theme/booking-block /-->' );

        if ( ! empty( $booking_blocks[0] ) ) {
            $blocks[] = $booking_blocks[0];
        }

        return $blocks;
    }

    private function get_elementor_booking_form( array $page ): array {
        $seed = (string) ( $page['id'] ?? ( $page['slug'] ?? ( $page['title'] ?? 'services' ) ) );

        return array(
            'id'       => substr( md5( 'hostinger-booking-section-' . $seed ), 0, 7 ),
            'elType'   => 'container',
            'isInner'  => false,
            'settings' => array(
                'content_width'  => 'boxed',
                'flex_direction' => 'column',
                'padding'        => array(
                    'unit'     => 'rem',
                    'top'      => '5',
                    'right'    => '0',
                    'bottom'   => '5',
                    'left'     => '0',
                    'isLinked' => false,
                ),
            ),
            'elements' => array(
                array(
                    'id'         => substr( md5( 'hostinger-booking-widget-' . $seed ), 0, 7 ),
                    'elType'     => 'widget',
                    'widgetType' => 'shortcode',
                    'isInner'    => true,
                    'settings'   => array(
                        'shortcode' => '[hostinger_booking_form]',
                    ),
                    'elements'   => array(),
                ),
            ),
        );
    }

    private function build_menu( array $menu, array $id_map, array $post_ids ): int {
        $pages = array();

        foreach ( $menu as $item ) {
            $page = is_array( $item ) ? $this->map_menu_item( $item, $id_map ) : null;
            if ( $page === null ) {
                continue;
            }

            $pages[] = $page;
        }

        if ( empty( $pages ) ) {
            foreach ( $post_ids as $post_id ) {
                $pages[] = array(
                    'title'   => get_the_title( $post_id ),
                    'page_id' => $post_id,
                );
            }
        }

        if ( empty( $pages ) ) {
            return 0;
        }

        return ( new NavigationBuilder( $pages ) )->updateMenus();
    }

    private function map_menu_item( array $item, array $id_map ): ?array {
        $page = $this->map_product_category_menu_item( $item );

        if ( $page === null ) {
            $envelope_id = (string) ( $item['page_id'] ?? '' );
            if ( $envelope_id === '' || ! isset( $id_map[ $envelope_id ] ) ) {
                return null;
            }

            $post_id = $id_map[ $envelope_id ];
            $page    = array(
                'title'   => (string) ( $item['title'] ?? get_the_title( $post_id ) ),
                'page_id' => $post_id,
            );
        }

        $children = array();

        foreach ( $item['children'] ?? array() as $child ) {
            $mapped_child = is_array( $child ) ? $this->map_menu_item( $child, $id_map ) : null;
            if ( $mapped_child !== null ) {
                $children[] = $mapped_child;
            }
        }

        if ( ! empty( $children ) ) {
            $page['children'] = $children;
        }

        return $page;
    }

    private function map_product_category_menu_item( array $item ): ?array {
        if ( ! array_key_exists( 'product_category', $item ) ) {
            return null;
        }

        $slug = sanitize_title( (string) $item['product_category'] );
        if ( $slug === '' ) {
            return null;
        }

        $term = get_term_by( 'slug', $slug, 'product_cat' );
        if ( ! $term instanceof WP_Term ) {
            $name  = (string) ( $item['title'] ?? ucwords( str_replace( '-', ' ', $slug ) ) );
            $terms = ( new ProductCategoryManager() )->ensure_category_terms(
                array(
                    array(
                        'name' => $name,
                        'slug' => $slug,
                    ),
                )
            );
            $term  = $terms[0] ?? null;
        }

        if ( ! $term instanceof WP_Term ) {
            return null;
        }

        $url = get_term_link( $term );
        if ( is_wp_error( $url ) ) {
            return null;
        }

        return array(
            'title'            => (string) ( $item['title'] ?? $term->name ),
            'product_category' => $term->slug,
            'term_id'          => (int) $term->term_id,
            'url'              => $url,
        );
    }

    private function apply_brand( array $brand, array $media_map ): string {
        $name = $brand['name'] ?? '';
        if ( is_string( $name ) && $name !== '' ) {
            update_option( 'blogname', sanitize_text_field( $name ) );
        }

        $tagline = $brand['tagline'] ?? null;
        if ( is_string( $tagline ) ) {
            update_option( 'blogdescription', sanitize_text_field( $tagline ) );
        }

        $logo_src = $brand['logo']['signed_url'] ?? '';
        if ( is_string( $logo_src ) && isset( $media_map[ $logo_src ] ) ) {
            $attachment_id = (int) $media_map[ $logo_src ]['id'];
            set_theme_mod( 'custom_logo', $attachment_id );
            update_option( BundleSchema::OPTION_ORIGINAL_LOGO, $attachment_id );

            return (string) $media_map[ $logo_src ]['url'];
        }

        return '';
    }

    private function apply_woocommerce( $woocommerce, array $media_map, array &$warnings ): int {
        if ( empty( $woocommerce ) || ! is_array( $woocommerce ) ) {
            return 0;
        }

        $products = new EnvelopeProducts();
        if ( ! $products->is_available() ) {
            $warnings[] = 'WooCommerce is not active — products and categories were skipped.';
            return 0;
        }

        $product_ids = $products->create( $woocommerce, $media_map );
        update_option( BundleSchema::OPTION_PRODUCTS, $product_ids );

        return count( $product_ids );
    }

    // Cart / checkout / my-account are not in the envelope.
    private function ensure_woocommerce_store_pages(): void {
        if ( ! get_option( 'hostinger_ai_woo', false ) ) {
            return;
        }

        $this->create_woocommerce_store_page(
            'cart.html',
            __( 'Cart', 'hostinger-ai-theme' ),
            'woocommerce_cart_page_id'
        );
        $this->create_woocommerce_store_page(
            'checkout.html',
            __( 'Checkout', 'hostinger-ai-theme' ),
            'woocommerce_checkout_page_id'
        );
        $this->create_woocommerce_store_page(
            'my-account.html',
            __( 'My Account', 'hostinger-ai-theme' ),
            'woocommerce_myaccount_page_id'
        );
    }

    private function create_woocommerce_store_page( string $template_file, string $title, string $option_name ): void {
        $editor = (string) get_option( 'hostinger_ai_builder_type', '' );

        if ( $editor === 'elementor' ) {
            $this->create_elementor_woocommerce_store_page( $template_file, $title, $option_name );
            return;
        }

        $this->create_gutenberg_woocommerce_store_page( $template_file, $title, $option_name );
    }

    private function create_gutenberg_woocommerce_store_page( string $template_file, string $title, string $option_name ): void {
        $template_path = dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR . 'blocks' . DIRECTORY_SEPARATOR . 'woo' . DIRECTORY_SEPARATOR . $template_file;

        if ( ! file_exists( $template_path ) ) {
            return;
        }

        $content = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( $content === false ) {
            return;
        }

        $page_id = Helper::insert_trusted_post(
            array(
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'meta_input'   => array(
                    GenerationConstant::META_KEY => '1',
                ),
            )
        );

        if ( is_wp_error( $page_id ) || ! $page_id ) {
            return;
        }

        update_option( $option_name, (int) $page_id );
        $this->record_created_page( sanitize_title( $title ), $title, (int) $page_id );
    }

    private function create_elementor_woocommerce_store_page( string $template_file, string $title, string $option_name ): void {
        $json_file     = str_replace( '.html', '.json', $template_file );
        $template_path = dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR . 'blocks' . DIRECTORY_SEPARATOR . 'elementor' . DIRECTORY_SEPARATOR . $json_file;

        if ( ! file_exists( $template_path ) ) {
            return;
        }

        $json_content = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( $json_content === false ) {
            return;
        }

        $page_id = Helper::insert_trusted_post(
            array(
                'post_title'   => $title,
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'meta_input'   => array(
                    GenerationConstant::META_KEY => '1',
                ),
            )
        );

        if ( is_wp_error( $page_id ) || ! $page_id ) {
            return;
        }

        $page_id = (int) $page_id;

        update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
        update_post_meta( $page_id, '_elementor_version', Helper::get_elementor_version() );
        Helper::save_elementor_data( $page_id, $json_content );

        update_option( $option_name, $page_id );
        $this->record_created_page( sanitize_title( $title ), $title, $page_id );
    }

    private function ensure_hostinger_ecommerce_shop_page( array &$envelope, string $editor, array &$pages ): void {
        $shop_page_id = $this->find_existing_shop_page( $envelope, $pages['id_map'] );

        if ( $shop_page_id <= 0 ) {
            $shop_page_id = $this->create_hostinger_ecommerce_shop_page( $editor );
        }

        if ( $shop_page_id <= 0 ) {
            return;
        }

        if ( ! in_array( $shop_page_id, $pages['post_ids'], true ) ) {
            $pages['post_ids'][] = $shop_page_id;
            update_option( BundleSchema::OPTION_PAGES, $pages['post_ids'] );
        }

        update_option( HostingerEcommerceHelper::PRODUCTS_PAGE_ID_OPTION, $shop_page_id );

        $this->ensure_hostinger_ecommerce_cart_page( $pages );

        $pages['id_map'][ self::HOSTINGER_ECOMMERCE_ENVELOPE_ID ] = $shop_page_id;
        $envelope['menu'] = $this->relink_shop_menu_items( $envelope['menu'] ?? array() );
    }

    private function ensure_hostinger_ecommerce_cart_page( array &$pages ): void {
        $cart_page_id = (int) get_option( HostingerEcommerceHelper::CART_PAGE_ID_OPTION, 0 );

        if ( $cart_page_id <= 0 || get_post_status( $cart_page_id ) !== 'publish' ) {
            $cart_page_id = $this->create_hostinger_ecommerce_cart_page();
        }

        if ( $cart_page_id <= 0 ) {
            return;
        }

        update_option( HostingerEcommerceHelper::CART_PAGE_ID_OPTION, $cart_page_id );

        if ( ! in_array( $cart_page_id, $pages['post_ids'], true ) ) {
            $pages['post_ids'][] = $cart_page_id;
            update_option( BundleSchema::OPTION_PAGES, $pages['post_ids'] );
        }
    }

    private function create_hostinger_ecommerce_cart_page(): int {
        $title   = __( 'Cart', 'hostinger-ai-theme' );
        $page_id = Helper::insert_trusted_post(
            array(
                'post_title'   => $title,
                'post_name'    => 'cart',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '[' . HostingerEcommerceHelper::CART_SHORTCODE . ']',
                'meta_input'   => array(
                    GenerationConstant::META_KEY => '1',
                ),
            )
        );

        if ( is_wp_error( $page_id ) || ! $page_id ) {
            return 0;
        }
        $page_id = (int) $page_id;

        update_post_meta( $page_id, '_wp_page_template', 'no-title' );
        update_post_meta( $page_id, '_hostinger_ai_generated', '1' );
        $this->record_created_page( 'cart', $title, $page_id );

        return $page_id;
    }

    private function find_existing_shop_page( array $envelope, array $id_map ): int {
        foreach ( $envelope['pages'] ?? array() as $page ) {
            if ( ! is_array( $page ) || sanitize_title( (string) ( $page['slug'] ?? '' ) ) !== 'shop' ) {
                continue;
            }

            $envelope_id = (string) ( $page['id'] ?? '' );
            $post_id     = $envelope_id !== '' ? (int) ( $id_map[ $envelope_id ] ?? 0 ) : 0;

            if ( $post_id > 0 ) {
                return $post_id;
            }
        }

        return 0;
    }

    private function create_hostinger_ecommerce_shop_page( string $editor ): int {
        $title   = __( 'Shop', 'hostinger-ai-theme' );
        $postarr = array(
            'post_title'   => $title,
            'post_name'    => 'shop',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
            'meta_input'   => array(
                GenerationConstant::META_KEY => '1',
            ),
        );

        if ( $editor === 'elementor' ) {
            $json = $this->load_hostinger_ecommerce_shop_template();
            if ( $json === '' ) {
                return 0;
            }

            $page_id = Helper::insert_trusted_post( $postarr );
            if ( is_wp_error( $page_id ) || ! $page_id ) {
                return 0;
            }
            $page_id = (int) $page_id;

            update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
            update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
            update_post_meta( $page_id, '_elementor_version', Helper::get_elementor_version() );
            update_post_meta( $page_id, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );
            Helper::save_elementor_data( $page_id, $json );
        } else {
            $postarr['post_content'] = HostingerEcommerceBuilder::PRODUCTS_BLOCK;

            $page_id = Helper::insert_trusted_post( $postarr );
            if ( is_wp_error( $page_id ) || ! $page_id ) {
                return 0;
            }
            $page_id = (int) $page_id;
        }

        update_post_meta( $page_id, '_wp_page_template', 'no-title' );
        update_post_meta( $page_id, '_hostinger_ai_generated', '1' );
        $this->record_created_page( 'shop', $title, $page_id );

        return $page_id;
    }

    private function load_hostinger_ecommerce_shop_template(): string {
        $path = dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR . 'blocks' . DIRECTORY_SEPARATOR . 'elementor' . DIRECTORY_SEPARATOR . HostingerEcommerceBuilder::SHOP_ELEMENTOR_TEMPLATE;

        if ( ! file_exists( $path ) ) {
            return '';
        }

        $content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        return $content !== false ? $content : '';
    }

    private function relink_shop_menu_items( array $menu ): array {
        foreach ( $menu as &$item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $is_shop = sanitize_title( (string) ( $item['product_category'] ?? '' ) ) === 'shop'
                || ( ! isset( $item['page_id'] ) && sanitize_title( (string) ( $item['title'] ?? '' ) ) === 'shop' );

            if ( $is_shop ) {
                unset( $item['product_category'] );
                $item['page_id'] = self::HOSTINGER_ECOMMERCE_ENVELOPE_ID;
            }

            if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
                $item['children'] = $this->relink_shop_menu_items( $item['children'] );
            }
        }

        return $menu;
    }

    private function apply_blog( $blog, MediaSideloader $media, array $media_map ): int {
        if ( empty( $blog ) || ! is_array( $blog ) ) {
            return 0;
        }

        $result = ( new EnvelopePosts( $media, null, $this->affiliate_builder ) )->create( $blog, $media_map );

        update_option( BundleSchema::OPTION_BLOG_POSTS, $result['post_ids'] );
        update_option( BundleSchema::OPTION_BLOG_CATS, $result['category_ids'] );

        return count( $result['post_ids'] );
    }

    private function ensure_pretty_permalinks(): void {
        if ( get_option( 'permalink_structure' ) ) {
            return;
        }

        global $wp_rewrite;

        update_option( 'permalink_structure', '/%postname%/' );

        if ( $wp_rewrite instanceof WP_Rewrite ) {
            $wp_rewrite->set_permalink_structure( '/%postname%/' );
        }
    }

    private function bust_caches( string $editor, array $page_ids ): void {
        if ( class_exists( WP_Theme_JSON_Resolver::class ) ) {
            WP_Theme_JSON_Resolver::clean_cached_data();
        }

        if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
            wp_clean_theme_json_cache();
        }

        $this->bust_rewrite_rules();
        $this->bust_object_and_page_caches();

        if ( $editor === 'elementor' ) {
            CssCache::clear( $page_ids );
        }
    }

    // New pages 404 until rewrite_rules is rebuilt.
    private function bust_rewrite_rules(): void {
        global $wp_rewrite;

        delete_option( 'rewrite_rules' );

        if ( $wp_rewrite instanceof WP_Rewrite ) {
            $wp_rewrite->flush_rules( false );
        }
    }

    // LiteSpeed object cache can keep serving the pre-apply palette.
    private function bust_object_and_page_caches(): void {
        if ( has_action( 'litespeed_purge_all' ) ) {
            do_action( 'litespeed_purge_all' );
        }

        if ( has_action( 'litespeed_purge_all_object' ) ) {
            do_action( 'litespeed_purge_all_object' );
        }

        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
    }
}
