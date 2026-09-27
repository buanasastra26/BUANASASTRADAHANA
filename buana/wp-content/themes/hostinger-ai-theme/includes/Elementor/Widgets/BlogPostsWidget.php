<?php

namespace Hostinger\AiTheme\Elementor\Widgets;

use Elementor;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class BlogPostsWidget extends Widget_Base {

    public function get_name(): string {
        return 'hostinger-blog-posts';
    }

    public function get_title(): string {
        return __( 'Blog Posts', 'hostinger-ai-theme' );
    }

    public function get_icon(): string {
        return 'eicon-posts-grid';
    }

    public function get_categories(): array {
        return [ 'general' ];
    }

    public function get_keywords(): array {
        return [ 'blog', 'posts', 'news', 'articles' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Content', 'hostinger-ai-theme' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_date',
            [
                'label'        => __( 'Show Date', 'hostinger-ai-theme' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Show', 'hostinger-ai-theme' ),
                'label_off'    => __( 'Hide', 'hostinger-ai-theme' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $args = [
            'post_type'      => 'post',
            'posts_per_page' => 2,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $posts = get_posts( $args );

        if ( empty( $posts ) ) {
            if ( Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<p>' . esc_html__( 'No posts found. Please create some blog posts.', 'hostinger-ai-theme' ) . '</p>';
            }
            return;
        }
        ?>
        <div class="hostinger-blog-posts-widget">
            <div class="blog-posts-grid">
                <?php foreach ( $posts as $post ) {
                    setup_postdata( $post ); ?>
                    <article class="blog-post-item">
                        <?php if ( has_post_thumbnail( $post->ID ) ) { ?>
                            <div class="post-thumbnail">
                                <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>">
                                    <?php echo get_the_post_thumbnail( $post->ID, 'large' ); ?>
                                </a>
                            </div>
                        <?php } ?>

                        <h3 class="post-title">
                            <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>">
                                <?php echo esc_html( get_the_title( $post->ID ) ); ?>
                            </a>
                        </h3>

                        <div class="post-excerpt">
                            <?php echo esc_html( wp_trim_words( get_the_excerpt( $post->ID ), 20, '...' ) ); ?>
                        </div>

                        <?php if ( 'yes' === $settings['show_date'] ) { ?>
                            <div class="post-date">
                                <?php echo esc_html( get_the_date( '', $post->ID ) ); ?>
                            </div>
                        <?php } ?>
                    </article>
                <?php } ?>
                <?php wp_reset_postdata(); ?>
            </div>
        </div>
        <?php
    }
}
