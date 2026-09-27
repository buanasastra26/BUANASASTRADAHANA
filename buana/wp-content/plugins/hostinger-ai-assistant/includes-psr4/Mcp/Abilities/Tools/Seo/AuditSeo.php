<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo;

use WP_Error;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class AuditSeo extends BaseSeoTool {
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT     = 100;
    public const TITLE_MAX     = 60;
    public const DESC_MAX      = 160;
    public const DESC_MIN      = 70;

    public const ISSUES = array(
        'missing_title',
        'missing_description',
        'title_too_long',
        'description_too_long',
        'description_too_short',
        'missing_canonical',
        'noindex',
    );

    public function register(): void {
        wp_register_ability(
            'hostinger-ai/seo-audit',
            array(
                'label'               => __( 'Audit SEO Metadata', 'hostinger-ai-assistant' ),
                'description'         => __( 'Find the posts and pages whose SEO metadata has problems. Start here for any site-wide SEO question rather than guessing which pages to check: "check my site SEO", "how is my SEO", "which pages are missing meta descriptions", "find SEO problems", "are any pages hidden from Google", "what should I improve". Reports a missing or overlong meta title, a missing, overlong or too-short meta description, and a noindex directive. "missing_canonical" is only checked by default on sites with no SEO plugin, because the SEO plugins derive canonicals at render time and leave the stored value empty; ask for it explicitly to check it anyway. Each entry gives you the post id plus its detected issues and its current values under "current"; to fix them, call seo-bulk-update with entries of the form {id, <field>: <replacement>} — lift the id out and put your replacements at the top level, do not send "current" back.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_type' => array(
                            'type'        => 'array',
                            'description' => __( 'Post types to audit. Defaults to posts and pages.', 'hostinger-ai-assistant' ),
                            'items'       => array( 'type' => 'string' ),
                        ),
                        'issues'    => array(
                            'type'        => 'array',
                            'description' => __( 'Only report these issue types. Defaults to all of them except missing_canonical, which is on by default only when no SEO plugin is active.', 'hostinger-ai-assistant' ),
                            'items'       => array(
                                'type' => 'string',
                                'enum' => self::ISSUES,
                            ),
                        ),
                        'limit'     => array(
                            'type'        => 'integer',
                            'description' => sprintf(
                                /* translators: 1: default number of posts examined, 2: maximum number of posts examined */
                                __( 'How many posts to examine, newest first. Default %1$d, maximum %2$d. This is a scan window, not a result count: posts without issues are left out of "posts", so fewer entries come back than the limit, and posts outside the window are not examined at all. Compare "scanned" against "total_available" (every post of the requested types, not just the problem ones) and page with "offset" to cover the whole site.', 'hostinger-ai-assistant' ),
                                self::DEFAULT_LIMIT,
                                self::MAX_LIMIT
                            ),
                            'default'     => self::DEFAULT_LIMIT,
                        ),
                        'offset'    => array(
                            'type'        => 'integer',
                            'description' => __( 'Number of matching posts to skip, for paging through results.', 'hostinger-ai-assistant' ),
                            'default'     => 0,
                        ),
                    ),
                ),
                'execute_callback'    => array( $this, 'execute' ),
                'permission_callback' => function (): bool {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => $this->ability_meta(
                    array(
                        'title'         => 'Audit SEO Metadata',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    )
                ),
            )
        );
    }

    public function execute( array $input ): WP_Error|array {
        $limit  = min( self::MAX_LIMIT, max( 1, absint( $input['limit'] ?? self::DEFAULT_LIMIT ) ) );
        $offset = absint( $input['offset'] ?? 0 );
        $issues = $this->requested_issues( $input['issues'] ?? array() );

        $query = new WP_Query(
            array(
                'post_type'              => $this->requested_post_types( $input['post_type'] ?? array() ),
                'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page'         => $limit,
                'offset'                 => $offset,
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'no_found_rows'          => false,
                'update_post_term_cache' => false,
            )
        );

        $provider = $this->resolver->get_primary();
        $posts    = array();

        foreach ( $query->posts as $post ) {
            $data  = $provider->read( $post->ID )->to_array();
            $found = $this->detect_issues( $data, $issues );

            if ( empty( $found ) ) {
                continue;
            }

            $posts[] = array(
                'id'        => $post->ID,
                'post_type' => $post->post_type,
                'title'     => $post->post_title,
                'url'       => get_permalink( $post ),
                'issues'    => $found,
                'current'   => $data,
            );
        }

        return array_merge(
            array(
                'posts'           => $posts,
                'limit'           => $limit,
                'offset'          => $offset,
                'scanned'         => count( $query->posts ),
                'total_available' => (int) $query->found_posts,
                'issues_checked'  => $issues,
            ),
            $this->provider_meta()
        );
    }

    private function requested_post_types( $requested ): array {
        if ( is_string( $requested ) && $requested !== '' ) {
            $requested = array( $requested );
        }

        if ( ! is_array( $requested ) || empty( $requested ) ) {
            return array( 'post', 'page' );
        }

        $available = get_post_types( array( 'public' => true ), 'names' );
        $filtered  = array_values( array_intersect( array_map( 'sanitize_key', $requested ), $available ) );

        return empty( $filtered ) ? array( 'post', 'page' ) : $filtered;
    }

    private function requested_issues( $requested ): array {
        if ( ! is_array( $requested ) || empty( $requested ) ) {
            return $this->default_issues();
        }

        $filtered = array_values( array_intersect( $requested, self::ISSUES ) );

        return empty( $filtered ) ? $this->default_issues() : $filtered;
    }

    private function default_issues(): array {
        if ( $this->resolver->get_primary()->get_key() === 'hostinger' ) {
            return self::ISSUES;
        }

        return array_values( array_diff( self::ISSUES, array( 'missing_canonical' ) ) );
    }

    private function detect_issues( array $data, array $checked ): array {
        $title       = (string) ( $data['seo_title'] ?? '' );
        $description = (string) ( $data['meta_description'] ?? '' );
        $canonical   = (string) ( $data['canonical'] ?? '' );
        $noindex     = ( $data['robots']['noindex'] ?? SeoData::ROBOTS_INHERIT ) === true;

        $detected = array();

        if ( $title === '' ) {
            $detected[] = 'missing_title';
        } elseif ( mb_strlen( $title ) > self::TITLE_MAX ) {
            $detected[] = 'title_too_long';
        }

        if ( $description === '' ) {
            $detected[] = 'missing_description';
        } elseif ( mb_strlen( $description ) > self::DESC_MAX ) {
            $detected[] = 'description_too_long';
        } elseif ( mb_strlen( $description ) < self::DESC_MIN ) {
            $detected[] = 'description_too_short';
        }

        if ( $canonical === '' ) {
            $detected[] = 'missing_canonical';
        }

        if ( $noindex ) {
            $detected[] = 'noindex';
        }

        return array_values( array_intersect( $detected, $checked ) );
    }
}
