<?php

namespace Hostinger\AiTheme;

defined( 'ABSPATH' ) || exit;

class Assets {
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'japanese_assets' ) );
        add_action( 'enqueue_block_assets', array( $this, 'japanese_assets' ) );
        add_action( 'after_setup_theme', array( $this, 'editor_styles' ) );
    }

    /**
     * Load the compiled theme stylesheet inside the block editor so the
     * decorative layer (card shadows / glass, masks, tilt, dividers, accent
     * shapes, background decor) renders while editing -- otherwise the editor
     * shows a plain page even though the front end is styled.
     *
     * @return void
     */
    public function editor_styles(): void {
        add_editor_style( 'assets/css/style.min.css' );
    }

    public function japanese_assets(): void {
        // Enqueue Japanese font if needed
        if ( $this->is_japanese_locale() ) {
            wp_enqueue_style(
                'noto-sans',
                'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100;300;400;500;700;900&display=swap',
                [],
                wp_get_theme()->get( 'Version' )
            );

            $japanese_font_css = "
                body,
                .wp-theme-hostinger-ai-theme, 
                .editor-styles-wrapper, 
                .block-editor-block-list__layout,
                .wp-block-paragraph,
                .wp-block-heading {
                    font-family: 'Noto Sans JP', -apple-system, BlinkMacSystemFont,
                        'Hiragino Sans', 'Hiragino Kaku Gothic ProN', 'Segoe UI',
                        'Yu Gothic UI', Meiryo, sans-serif;
                    font-weight: 400;
                    -webkit-font-smoothing: antialiased;
                    -moz-osx-font-smoothing: grayscale;
                    word-break: keep-all;
                },
                .entry-content p,
			    .entry-content h1,
			    .entry-content h2,
			    .entry-content h3,
			    .entry-content h4,
			    .entry-content h5,
			    .entry-content h6,
			    nav,
			    footer {
			        word-spacing: -0.25em;
			    }
            ";

            wp_add_inline_style( 'noto-sans', wp_strip_all_tags( $japanese_font_css ) );

            wp_register_script(
                'hostinger-ai-japanese-segmenter',
                '',
                [],
                wp_get_theme()->get( 'Version' ),
            );

            $word_segmentation_script = "
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.Intl && Intl.Segmenter) {
                        const segmenter = new Intl.Segmenter('ja-JP', { granularity: 'word' });
                        const elements = document.querySelectorAll('h1, h2, h3, h4, h5, h6, p, .wp-block-paragraph, .wp-block-heading, .wp-block-quote, .wp-block-pullquote, .wp-block-verse, .wp-block-preformatted, .wp-block-table, .wp-block-button__link, .wp-block-media-text__content, .wp-block-cover__inner-container, .wp-block-columns, .wp-block-column, .wp-block-group__inner-container, .wp-block-navigation-item__content, .wp-block-latest-posts__post-title, .wp-block-latest-posts__post-excerpt, .wp-block-latest-comments__comment-excerpt, .wp-block-gallery figcaption, .blocks-gallery-caption, .wp-block-image figcaption, .wp-block-embed figcaption, .wp-block-search__label, .wp-block-search__input, .wp-block-table caption, .wp-block-latest-posts__post-author, .wp-block-latest-posts__post-date, .wp-block-latest-comments__comment-author, .wp-block-latest-comments__comment-date');
                        
                        function segmentTextNodes(node) {
                            node.childNodes.forEach((child) => {
                                if (child.nodeType === Node.TEXT_NODE && child.nodeValue.trim()) {
                                    const segments = segmenter.segment(child.nodeValue);
                                    child.nodeValue = Array.from(segments).map(segment => segment.segment).join(' ');
                                } else if (child.nodeType === Node.ELEMENT_NODE) {
                                    segmentTextNodes(child); // Recursively process child elements
                                }
                            });
                        }
                        
                        elements.forEach(segmentTextNodes);
                    } else {
                        console.warn('Intl.Segmenter is not supported in this browser.');
                    }
                });
            ";

            // Add the script code and enqueue it
            wp_add_inline_script('hostinger-ai-japanese-segmenter', wp_strip_all_tags($word_segmentation_script));
            wp_enqueue_script('hostinger-ai-japanese-segmenter');
        }
    }

    /**
     * Check if current locale is Japanese
     * @return bool
     */
    private function is_japanese_locale(): bool {
        $japanese_locales = array( 'ja', 'ja_JP' );

        return in_array( get_locale(), $japanese_locales, true );
    }

    /**
     * Enqueue frontend styles
     * @return void
     */
    public function frontend_styles(): void {
        wp_enqueue_style(
            'hostinger-ai-style',
            get_stylesheet_directory_uri() . '/assets/css/style.min.css',
            [],
            wp_get_theme()->get( 'Version' ),
        );

        if( !is_admin() ) {
            wp_add_inline_style(
                'hostinger-ai-style',
                '.hostinger-ai-fade-up { opacity: 0; }'
            );
        }

        $this->output_font_css();
    }

    private function output_font_css(): void {
        $heading_font = get_option( 'hostinger_ai_font', false );
        $body_font    = get_option( 'hostinger_ai_body_font', false );

        if ( ! $heading_font || ! $body_font ) {
            return;
        }

        $css = sprintf(
            '.hostinger-ai-font-title { font-family: %s; } body.elementor-page { font-family: %s; }',
            sanitize_text_field( $heading_font ),
            sanitize_text_field( $body_font )
        );

        wp_add_inline_style( 'hostinger-ai-style', $css );
    }

    /**
     * @return void
     */
    public function frontend_scripts(): void {
        wp_enqueue_script(
            'hostinger-ai-scripts',
            get_stylesheet_directory_uri() . '/assets/js/front-scripts.min.js',
            [
                'jquery',
                'wp-i18n',
            ],
            wp_get_theme()->get( 'Version' ),
            true,
        );
    }
}
