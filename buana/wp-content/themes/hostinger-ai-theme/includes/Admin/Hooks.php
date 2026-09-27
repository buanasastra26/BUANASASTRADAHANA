<?php

namespace Hostinger\AiTheme\Admin;

use Hostinger\AiTheme\Builder\ImageManager;
use Hostinger\AiTheme\Constants\PreviewImageConstant;
use WP_Post;

defined( 'ABSPATH' ) || exit;

class Hooks {
    private ImageManager $image_manager;
    public function __construct( ImageManager $image_manager ) {
        $this->image_manager = $image_manager;

        add_action( 'add_meta_boxes', array( $this, 'add_preview_image_metabox' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_page_title_visibility_metabox' ) );
        add_action( 'wp_insert_post_data', array( $this, 'save_preview_image_url' ), 0, 2 );
        add_action( 'save_post', array( $this, 'save_page_title_visibility' ), 10, 2 );
        add_action( 'admin_init', array( $this, 'redirect_to_elementor_homepage_edit' ) );
    }

    /**
     * @param $post_type
     *
     * @return void
     */
    public function add_preview_image_metabox( $post_type ): void {
        if ( ! in_array( $post_type, PreviewImageConstant::ALLOWED_POST_TYPES, true ) ) {
            return;
        }

        if ( ! post_type_supports( $post_type, 'thumbnail' ) ) {
            return;
        }

        add_meta_box(
            'hostinger_metabox',
            __( 'Featured Image with URL', 'hostinger-ai-theme' ),
            array( $this, 'render_metabox' ),
            $post_type,
            'normal',
            'low'
        );
    }

    /**
     * @param $post
     *
     * @return void
     */
    public function render_metabox( $post ): void {
        $image_url = get_post_meta( $post->ID, PreviewImageConstant::META_SLUG, true );

        include get_stylesheet_directory() . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR . 'featured-image-metabox.php';
    }

    public function save_preview_image_url( array $data, array $post_data ): array {
        $post_id = !empty( $post_data['post_ID'] ) ? $post_data['post_ID'] : 0;

        if ( empty( $post_id ) ) {
            return $data;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) || ! post_type_supports( $post_data['post_type'], 'thumbnail' ) || defined( 'DOING_AUTOSAVE' ) ) {
            return $data;
        }


        if ( isset( $_POST[ PreviewImageConstant::META_SLUG ] ) ) {
            $image_url = isset( $_POST[ PreviewImageConstant::META_SLUG ] ) ? esc_url_raw( wp_unslash( $_POST[ PreviewImageConstant::META_SLUG ] ) ) : '';

            $this->handle_external_url_field( $image_url, $post_id );
        }

        $featured_image_id = get_post_meta( $post_id, '_thumbnail_id', true );
        $preview_attachment_id = get_post_meta( $post_id, PreviewImageConstant::ATTACHMENT_ID, true );

        if ( ! empty( $featured_image_id ) && $featured_image_id !== $preview_attachment_id ) {
            $this->image_manager->clean_external_image_data( $post_id );
        }

        return $data;
    }

    public function redirect_to_elementor_homepage_edit(): void {
        if ( empty( $_GET['hostinger_builder_redirect'] ) ) {
            return;
        }

        $builder_type = get_option( 'hostinger_ai_builder_type', 'gutenberg' );
        $homepage_id = get_option( 'page_on_front' );
        if ( empty( $homepage_id ) ) {
            return;
        }

        if ( $builder_type !== 'elementor' ) {
            $query_args = array(
                'canvas' => 'edit',
            );

            wp_redirect( add_query_arg( $query_args, admin_url( 'site-editor.php' ) ) );
            exit;
        }

        wp_redirect( admin_url( 'post.php?post=' . $homepage_id . '&action=elementor' ) );
        exit;
    }

    private function handle_external_url_field( string $image_url, int $post_id ) : void {
        update_post_meta( $post_id, PreviewImageConstant::META_SLUG, $image_url );

        if ( empty( $image_url ) ) {
            $this->image_manager->clean_external_image_data( $post_id );
            return;
        }

        $attachments = $this->image_manager->get_attachments_by_meta_value( PreviewImageConstant::POST_ID, $post_id );

        if ( empty( $attachments ) ) {
            $this->image_manager->create_image_placeholder_attachment( $post_id );
        }
    }

    public function add_page_title_visibility_metabox( string $post_type ): void {
        if ( $post_type !== 'page' ) {
            return;
        }

        $builder_type = get_option( 'hostinger_ai_builder_type', 'gutenberg' );

        if ( $builder_type === 'elementor' ) {
            return;
        }

        add_meta_box(
            'hostinger_page_title_visibility',
            __( 'Page Title Display', 'hostinger-ai-theme' ),
            array( $this, 'render_page_title_visibility_metabox' ),
            $post_type,
            'side',
            'default'
        );
    }

    public function render_page_title_visibility_metabox( WP_Post $post ): void {
        $current_template = get_post_meta( $post->ID, '_wp_page_template', true );
        $hide_title       = ( $current_template === 'no-title' );

        wp_nonce_field( 'hostinger_page_title_visibility', 'hostinger_page_title_visibility_nonce' );
        ?>
        <p>
            <label>
                <input type="checkbox" name="hostinger_hide_page_title" value="1" <?php checked( $hide_title, true ); ?> />
                <?php esc_html_e( 'Hide page title', 'hostinger-ai-theme' ); ?>
            </label>
        </p>
        <p class="description">
            <?php esc_html_e( 'Check this box to hide the page title on the frontend. This is useful when your page content already includes a heading.', 'hostinger-ai-theme' ); ?>
        </p>
        <?php
    }

    public function save_page_title_visibility( int $post_id, WP_Post $post ): void {
        $nonce       = isset( $_POST['hostinger_page_title_visibility_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['hostinger_page_title_visibility_nonce'] ) )
            : '';
        $is_autosave = defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;

        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'hostinger_page_title_visibility' ) || $is_autosave || ! current_user_can( 'edit_post', $post_id ) || $post->post_type !== 'page' ) {
            return;
        }

        $hide_title = isset( $_POST['hostinger_hide_page_title'] )
            ? sanitize_text_field( wp_unslash( $_POST['hostinger_hide_page_title'] ) )
            : '';

        if ( $hide_title === '1' ) {
            update_post_meta( $post_id, '_wp_page_template', 'no-title' );
        } else {
            delete_post_meta( $post_id, '_wp_page_template' );
        }
    }
}

