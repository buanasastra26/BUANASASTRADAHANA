<?php

namespace Hostinger\AiTheme;

use Hostinger\AiTheme\Builder\BundleSchema;
use Hostinger\AiTheme\Builder\Elementor\KitManager;
use Hostinger\AiTheme\Builder\Fonts;
use Hostinger\AiTheme\Builder\ImageManager;
use Hostinger\AiTheme\Constants\PreviewImageConstant;
use WP_Admin_Bar;
use stdClass;
use WP_REST_Request;
use WP_REST_Response;
use WP_Post;
use WC_Product;

defined( 'ABSPATH' ) || exit;

class Hooks {
    private ImageManager $image_manager;
    public function __construct( ImageManager $image_manager ) {
        $this->image_manager = $image_manager;

        $this->ensure_elementor_page_title_selector();
        $this->ensure_elementor_typography();

        if ( isset( $_GET['ai_preview'] ) || isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] == 'iframe' ) {
            add_filter( 'show_admin_bar', '__return_false' );
            add_action( 'wp_head', array( $this, 'hide_preview_domain_topbar' ), 999 );
        }

        add_filter( 'render_block', array( $this, 'replace_scroll_fade_class' ), 10, 2 );

        $this->rehook_edit_site_menu();

        $this->register_image_sizes();

        if ( ! is_admin() || (( defined( 'DOING_AJAX' ) && DOING_AJAX )) ) {
            add_filter( 'get_post_metadata', array( $this, 'set_thumbnail_id_true' ), 10, 3 );
            add_filter( 'wp_get_attachment_image_src', array( $this, 'replace_image_src' ), 10, 3 );
        }

        if ( is_admin() && (
            (isset( $_GET['post_type'] ) && $_GET['post_type'] === 'product') ||
            (isset( $_GET['post'] ) && get_post_type( $_GET['post'] ) === 'product') ||
            (isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['post'] ) && get_post_type( $_GET['post'] ) === 'product')
        )) {
            add_filter( 'get_post_metadata', array( $this, 'set_thumbnail_id_true' ), 10, 3 );
            add_filter( 'wp_get_attachment_image_src', array( $this, 'replace_image_src' ), 10, 3 );
        }

        if ( class_exists( 'WooCommerce' ) ) {
            add_filter( 'woocommerce_product_get_image_id', array( $this, 'get_product_image_id' ), 10, 2 );
            add_filter( 'woocommerce_product_get_image', array( $this, 'replace_product_image' ), 10, 6 );
            add_filter( 'woocommerce_store_api_cart_item_images', array( $this, 'replace_cart_product_image' ), 10, 3 );
        }

        add_filter( 'rest_prepare_attachment', array( $this, 'replace_attachment_url' ), 10, 2 );
        add_filter( 'rest_pre_insert_post', array( $this, 'catch_featured_image_change' ), 10, 2 );
        add_filter( 'wp_handle_sideload_prefilter', array( $this, 'validate_logo_upload' ) );
        add_action( 'delete_post_product', array( $this, 'clean_preview_image_data' ), 99, 2 );
    }

    public function replace_cart_product_image(array $product_images, array $cart_item, string $cart_item_key): array {
        if (empty($cart_item['product_id'])) {
            return $product_images;
        }

        $product_id = $cart_item['product_id'];
        $image_url = get_post_meta($product_id, PreviewImageConstant::META_SLUG, true);

        if (empty($image_url)) {
            return $product_images;
        }

        $structure = [
            'image_size' => [
                'width' => 700,
                'height' => 320,
                'crop' => true
            ]
        ];

        $image_manager = new ImageManager();
        $full_url = $image_manager->modify_image_url($image_url, $structure);

        $thumb_structure = [
            'image_size' => [
                'width' => 300,
                'height' => 300,
                'crop' => true
            ]
        ];
        $thumbnail_url = $image_manager->modify_image_url($image_url, $thumb_structure);

        $title = get_the_title($product_id);

        $new_image = new stdClass();
        $new_image->id = get_post_meta($product_id, PreviewImageConstant::ATTACHMENT_ID, true) ?: 0;
        $new_image->src = $full_url;
        $new_image->thumbnail = $thumbnail_url;
        $new_image->srcset = "$full_url 700w, $thumbnail_url 300w";
        $new_image->sizes = '(max-width: 700px) 100vw, 700px';
        $new_image->name = $title;
        $new_image->alt = esc_attr($title);

        return [$new_image];
    }


    /**
     * @return void
     */
    public function rehook_edit_site_menu() {
        add_action( 'admin_bar_menu', [ $this, 'add_edit_site_menu' ], 41 );
    }

    /**
     * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
     *
     * @return void
     */
    public function add_edit_site_menu( WP_Admin_Bar $wp_admin_bar ) {

        if ( ! wp_is_block_theme() || ! current_user_can( 'edit_theme_options' ) ) {
            return;
        }

        $front_page_id = get_option( 'page_on_front' );

        if( is_front_page() || get_the_id() == $front_page_id ) {
            $wp_admin_bar->remove_menu('site-editor');
            return;
        }

        if ( ! $front_page_id ) {
            return;
        }

        $query_args = array(
            'post'   => $front_page_id,
            'action' => 'edit',
        );

        $builder_type = get_option( 'hostinger_ai_builder_type', 'elementor' );
        if ( $builder_type === 'elementor' ) {
            $query_args['action'] = 'elementor';
        }

        $edit_url = add_query_arg( $query_args, admin_url( 'post.php' ) );

        $wp_admin_bar->add_node(
            array(
                'id'    => 'site-editor',
                'title' => __( 'Edit site', 'hostinger-ai-theme' ),
                'href'  => $edit_url,
            )
        );

    }

    /**
     * @return void
     */
    public function hide_preview_domain_topbar() {
        ?>
        <style>
            #hostinger-preview-banner {
                display: none!important;
                box-sizing: border-box!important;
            }
        </style>
        <?php
    }

    /**
     * @return void
     */
    public function replace_scroll_fade_class( $block_content, $block ) {
        // Check if the block content contains the class wp-block-group
        if ( strpos( $block_content, 'hostinger-ai-fade-up' ) !== false ) {
            // Add the data-aos attribute to the block content
            $block_content = preg_replace(
                '/(<\w+\s+[^>]*class="[^"]*hostinger-ai-fade-up[^"]*")/',
                '$1 data-aos="fade-up"',
                $block_content,
                1
            );
        }

        return $block_content;
    }

    public function register_image_sizes(): void {
        add_image_size( 'blog-thumb', 530, 250, array( 'center', 'center' ) );
        add_image_size( 'blog-full', 1100, 450, array( 'center', 'center' ) );
    }

    /**
     * @param $value
     * @param $object_id
     * @param $meta_key
     *
     * @return mixed
     */
    public function set_thumbnail_id_true( $value, $object_id, $meta_key ) {
        $post_type = get_post_type( $object_id );

        if ( ! in_array( $post_type, PreviewImageConstant::ALLOWED_POST_TYPES, true ) || isset( $_POST['_wp_http_referer'] ) ) {
            return $value;
        }

        if ( $meta_key === '_thumbnail_id' ) {
            $attach_id = get_post_meta( $object_id, PreviewImageConstant::ATTACHMENT_ID, true );

            if ( ! empty( $attach_id ) ) {
                return $attach_id;
            }
        }

        return $value;
    }

    public function replace_image_src( mixed $image, mixed $attachment_id, mixed $size ): mixed {
        if ( ! is_numeric( $attachment_id ) || $attachment_id <= 0 ) {
            return $image;
        }

	    $attachment_id = (int) $attachment_id;

        $post_id = get_post_meta( $attachment_id, PreviewImageConstant::POST_ID, true );

        if ( empty( $post_id ) ) {
            return $image;
        }

        $image_url = get_post_meta( $post_id, PreviewImageConstant::META_SLUG, true );

        if ( empty( $image_url ) ) {
            return $image;
        }

        // Handle different size formats (array or string)
        if (is_array($size)) {
            $width = !empty($size[0]) ? $size[0] : '';
            $height = !empty($size[1]) ? $size[1] : '';
            $crop = !empty($size[2]) ? $size[2] : false;

            $structure = array(
                'image_size' => array(
                    'width' => $width,
                    'height' => $height,
                    'crop' => $crop
                )
            );

            $image_manager = new ImageManager();
            $cropped_url = $image_manager->modify_image_url($image_url, $structure);

            return array(
                $cropped_url,
                $width,
                $height,
                $crop
            );
        } else {
            $cropped_image_url = $this->crop_external_image( $image_url, $size );
            return !empty( $cropped_image_url ) ? $cropped_image_url : $image;
        }
    }

    public function crop_external_image( string $image_url, string $size ): array {
        global $_wp_additional_image_sizes;

        $image_sizes = $_wp_additional_image_sizes;

        $default_image_sizes = array( 'thumbnail', 'medium', 'large' );

        foreach ( $default_image_sizes as $size_item ) {
            $image_sizes[$size_item]['width']	= intval( get_option( "{$size_item}_size_w") );
            $image_sizes[$size_item]['height'] = intval( get_option( "{$size_item}_size_h") );
            $image_sizes[$size_item]['crop']	= get_option( "{$size_item}_crop" ) ? get_option( "{$size_item}_crop" ) : false;
        }

        $image_size = !empty($image_sizes[ $size ]) ? $image_sizes[ $size ] : '';

        if(!empty($image_size)) {
            $structure = array(
                'image_size' => $image_size
            );

            $image_manager = new ImageManager();

            $image_url = $image_manager->modify_image_url( $image_url, $structure );

            return array(
                $image_url,
                $image_size['width'],
                $image_size['height'],
                $image_size['crop'],
            );
        }

        return array();
    }

    public function replace_attachment_url( WP_REST_Response $response, WP_Post $post ): WP_REST_Response {
        $post_id = get_post_meta( $post->ID, PreviewImageConstant::POST_ID, true );

        if ( ! empty( $post_id ) ) {
            $external_url = get_post_meta( $post_id, PreviewImageConstant::META_SLUG, true );

            if( ! empty( $external_url ) ) {
                $cropped_image_url = $this->crop_external_image( $external_url, 'blog-thumb' );

                $response->data['guid']['rendered'] = $cropped_image_url;
                $response->data['source_url'] = $cropped_image_url;
                $response->data['media_details'] = array(
                        'blog-thumb' => array(
                                'source_url' => $cropped_image_url
                        )
                );
            }
        }

        return $response;
    }

    public function catch_featured_image_change( stdClass $prepared_post, WP_REST_Request $request ): stdClass {
        $params = $request->get_params();

        $attachment_id = (int)get_post_meta( $prepared_post->ID, PreviewImageConstant::ATTACHMENT_ID, true );
        $featured_image_id = ! empty( $params['featured_media'] ) ? (int)$params['featured_media'] : 0;

        if ( ! empty( $featured_image_id ) && $attachment_id !== $featured_image_id ) {
            $this->image_manager->clean_external_image_data( $prepared_post->ID );
        }

        return $prepared_post;
    }

    public function get_product_image_id( mixed $image_id, WC_Product $product ) {
        if ( ! method_exists( $product, 'get_id' ) ) {
            return $image_id;
        }

        $product_id = $product->get_id();

        $attach_id = get_post_meta( $product_id, PreviewImageConstant::ATTACHMENT_ID, true );

        if ( ! empty( $attach_id ) ) {
            return $attach_id;
        }

        return $image_id;
    }

    public function replace_product_image( string $image, WC_Product $product, mixed $size, array $attr, bool $placeholder, string $image_srcset ): array|string {
        if ( ! method_exists( $product, 'get_id' ) ) {
            return $image;
        }

        $product_id = $product->get_id();
        $image_url = get_post_meta( $product_id, PreviewImageConstant::META_SLUG, true );
        $attachment_id = get_post_meta( $product_id, PreviewImageConstant::ATTACHMENT_ID, true );

        if ( empty( $image_url ) || empty( $attachment_id ) ) {
            return $image;
        }

        if ( is_array( $size ) ) {
            $size = 'thumbnail';
        }

        $modified_image = $this->crop_external_image( $image_url, $size );

        if ( empty( $modified_image ) ) {
            return $image;
        }

        $attributes = array(
            'src'    => $modified_image[0],
            'width'  => $modified_image[1],
            'height' => $modified_image[2],
            'class'  => $attr['class'] ?? 'wp-post-image',
            'alt'    => $attr['alt'] ?? get_the_title( $product_id ),
        );

        $img_html = '<img';
        foreach ( $attributes as $name => $value ) {
            $img_html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
        }
        $img_html .= '/>';

        if ( strpos( $image, '<div' ) === 0 ) {
            preg_match( '/<div[^>]*>(.*?)<\/div>/s', $image, $matches );
            if ( !empty( $matches[0] ) ) {
                return str_replace( $matches[1], $img_html, $image );
            }
        }

        return $img_html;
    }

    public function clean_preview_image_data( int $post_id, WP_Post $post ): void {
        if ( !in_array( get_post_type( $post_id ), PreviewImageConstant::ALLOWED_POST_TYPES, true ) ) {
            return;
        }

        $this->image_manager->clean_external_image_data( $post_id );
    }

    public function validate_logo_upload( array $file ): array {
        if ( ! isset( $_REQUEST['upload_context'] ) || $_REQUEST['upload_context'] !== 'logo' ) {
            return $file;
        }

        $allowed_extensions = array( 'jpg', 'jpeg', 'png', 'svg' );
        $filetype           = wp_check_filetype( $file['name'] );
        if ( empty( $filetype['ext'] ) || ! in_array( $filetype['ext'], $allowed_extensions, true ) ) {
            $file['error'] = __( 'Invalid file type. Allowed types: jpg, png, svg.', 'hostinger-ai-theme' );
            return $file;
        }

        $max_size  = 2 * 1024 * 1024;
        $file_size = ! empty( $file['tmp_name'] ) ? filesize( $file['tmp_name'] ) : 0;
        if ( $file_size > $max_size ) {
            $file['error'] = __( 'File size exceeds the 2 MB limit.', 'hostinger-ai-theme' );
            return $file;
        }

        return $file;
    }

    private function ensure_elementor_typography(): void {
        $heading_font    = get_option( 'hostinger_ai_font', false );
        $is_elementor    = get_option( 'hostinger_ai_builder_type', 'gutenberg' ) === 'elementor';
        $kit_id          = get_option( 'elementor_active_kit' );
        $typography_font = get_option( 'hostinger_elementor_typography_set' );

        if ( ! $heading_font || ! $is_elementor || ! did_action( 'elementor/loaded' ) || empty( $kit_id ) ) {
            return;
        }

        if ( $typography_font === $heading_font ) {
            return;
        }

        $theme_json_data = [ 'settings' => wp_get_global_settings() ];
        $body_font       = ( new Fonts() )->get_body_font( $theme_json_data, $heading_font );

        if ( BundleSchema::is_active() ) {
            ( new KitManager() )->apply_typography_families( $heading_font, $body_font );
            return;
        }

        ( new KitManager() )->transform_custom_typography( $heading_font, $body_font );
    }

    private function ensure_elementor_page_title_selector(): void {
        $is_already_set = get_option( 'hostinger_page_title_selector_set' );
        $is_elementor   = get_option( 'hostinger_ai_builder_type', 'gutenberg' ) === 'elementor';
        $kit_id         = get_option( 'elementor_active_kit' );

        if ( $is_already_set || ! $is_elementor || ! did_action( 'elementor/loaded' ) || empty( $kit_id ) ) {
            return;
        }

        $settings = get_post_meta( $kit_id, '_elementor_page_settings', true );

        if ( empty( $settings['page_title_selector'] ) ) {
            $kit_manager = new KitManager();
            $kit_manager->set_page_title_selector( '.entry-title, .wp-block-post-title' );
        }

        update_option( 'hostinger_page_title_selector_set', true );
    }
}
