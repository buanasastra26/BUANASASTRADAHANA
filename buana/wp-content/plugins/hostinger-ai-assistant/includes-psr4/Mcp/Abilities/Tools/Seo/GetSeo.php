<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class GetSeo extends BaseSeoTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/seo-get',
            array(
                'label'               => __( 'Get SEO Metadata', 'hostinger-ai-assistant' ),
                'description'         => __( 'Read the SEO metadata of one post or page. Use this when asked what SEO settings a specific page has, or before rewriting them: "what is the SEO title of this page", "does this post have a meta description", "is this page hidden from Google", "what is the focus keyword", "what image is used when this page is shared". Returns the meta title, meta description, canonical URL, robots directives (noindex, nofollow, noarchive), focus keyword and social preview (Open Graph and X/Twitter) fields. This tool only reads and only handles one post: use seo-update to change values, seo-bulk-update for several posts, and seo-audit to find which posts across the site have SEO problems. Reads from the active SEO plugin (Yoast SEO, Rank Math or All in One SEO) and falls back to Hostinger storage when no SEO plugin is installed. The response reports which provider answered.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'id' => array(
                            'type'        => 'integer',
                            'description' => __( 'ID of the post or page.', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'id' ),
                ),
                'execute_callback'    => array( $this, 'execute' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'meta'                => $this->ability_meta(
                    array(
                        'title'         => 'Get SEO Metadata',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    )
                ),
            )
        );
    }

    public function check_permission( $input ): bool {
        return $this->can_edit( $input['id'] ?? 0 );
    }

    public function execute( array $input ): WP_Error|array {
        $post = $this->resolve_post( $input['id'] ?? 0 );

        if ( is_wp_error( $post ) ) {
            return $post;
        }

        $data = $this->resolver->get_primary()->read( $post->ID )->to_array();

        if ( ! isset( $data['robots'] ) ) {
            $data['robots'] = array(
                'noindex'   => SeoData::ROBOTS_INHERIT,
                'nofollow'  => SeoData::ROBOTS_INHERIT,
                'noarchive' => SeoData::ROBOTS_INHERIT,
            );
        }

        return array_merge(
            array(
                'id'        => $post->ID,
                'post_type' => $post->post_type,
                'url'       => get_permalink( $post ),
            ),
            $data,
            $this->provider_meta()
        );
    }
}
