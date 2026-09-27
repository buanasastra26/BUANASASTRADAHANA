<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo;

use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

abstract class BaseSeoTool {
    protected string $category = 'hostinger-ai-assistant';
    protected string $type     = 'tool';
    protected ProviderResolver $resolver;

    public function __construct( ?ProviderResolver $resolver = null ) {
        $this->resolver = $resolver ?? new ProviderResolver();
    }

    abstract public function register(): void;

    protected function seo_field_properties(): array {
        return array(
            'seo_title'           => array(
                'type'        => 'string',
                'description' => __( 'Meta title shown in search results. Pass an empty string to clear it.', 'hostinger-ai-assistant' ),
            ),
            'meta_description'    => array(
                'type'        => 'string',
                'description' => __( 'Meta description shown in search results. Aim for 70-160 characters.', 'hostinger-ai-assistant' ),
            ),
            'canonical'           => array(
                'type'        => 'string',
                'description' => __( 'Absolute canonical URL for this post.', 'hostinger-ai-assistant' ),
            ),
            'focus_keyword'       => array(
                'type'        => 'string',
                'description' => __( 'Primary focus keyword for this post.', 'hostinger-ai-assistant' ),
            ),
            'robots'              => array(
                'type'        => 'object',
                'description' => __( 'Search engine robots directives. Each directive is true, false, or the string "default" to clear the per-post setting so the site-wide default applies again. Passing false pins an explicit positive directive, which a later change to the site default will not override — use "default" when you mean "stop overriding". On sites using All in One SEO, setting any one directive pins all three for that post, because it stores a single all-or-nothing "use defaults" flag; and a request mixing "default" with an explicit value is refused there rather than silently inverted.', 'hostinger-ai-assistant' ),
                'properties'  => array(
                    'noindex'   => $this->robots_directive_schema(),
                    'nofollow'  => $this->robots_directive_schema(),
                    'noarchive' => $this->robots_directive_schema(),
                ),
            ),
            'og_title'            => array(
                'type'        => 'string',
                'description' => __( 'Open Graph title used by Facebook and LinkedIn previews.', 'hostinger-ai-assistant' ),
            ),
            'og_description'      => array(
                'type'        => 'string',
                'description' => __( 'Open Graph description used by social previews.', 'hostinger-ai-assistant' ),
            ),
            'og_image'            => array(
                'type'        => 'string',
                'description' => __( 'Absolute URL of the Open Graph preview image.', 'hostinger-ai-assistant' ),
            ),
            'twitter_title'       => array(
                'type'        => 'string',
                'description' => __( 'Title used by X/Twitter card previews.', 'hostinger-ai-assistant' ),
            ),
            'twitter_description' => array(
                'type'        => 'string',
                'description' => __( 'Description used by X/Twitter card previews.', 'hostinger-ai-assistant' ),
            ),
            'twitter_image'       => array(
                'type'        => 'string',
                'description' => __( 'Absolute URL of the X/Twitter card image.', 'hostinger-ai-assistant' ),
            ),
        );
    }

    protected function robots_directive_schema(): array {
        return array(
            'type' => array( 'boolean', 'string' ),
            'enum' => array( true, false, SeoData::ROBOTS_INHERIT ),
        );
    }

    protected function resolve_post( $post_id ): WP_Post|WP_Error {
        $post_id = absint( $post_id );

        if ( ! $post_id ) {
            return new WP_Error(
                'seo_invalid_post_id',
                __( 'A valid post ID is required.', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        $post = get_post( $post_id );

        if ( ! $post instanceof WP_Post ) {
            return new WP_Error(
                'seo_post_not_found',
                __( 'No post found with that ID.', 'hostinger-ai-assistant' ),
                array( 'status' => 404 )
            );
        }

        if ( wp_is_post_revision( $post ) || $post->post_status === 'auto-draft' ) {
            return new WP_Error(
                'seo_post_not_addressable',
                __( 'SEO metadata cannot be set on a revision or an auto-draft, because that row is never rendered.', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        return $post;
    }

    /**
     * @return array{written_to: string[], skipped_fields: string[], skipped_by: array<string, string[]>}
     */
    protected function write( int $post_id, SeoData $data ): array {
        $written_to = array();
        $skipped    = array();
        $skipped_by = array();

        foreach ( $this->resolver->get_writable() as $provider ) {
            $result = $provider->write( $post_id, $data );

            if ( ! empty( $result['written'] ) ) {
                $written_to[] = $provider->get_key();
            }

            foreach ( $result['skipped'] ?? array() as $field ) {
                $skipped[]              = $field;
                $skipped_by[ $field ][] = $provider->get_key();
            }
        }

        foreach ( $data->rejected_fields() as $field ) {
            $skipped[]              = $field;
            $skipped_by[ $field ][] = 'input';
        }

        return array(
            'written_to'     => $written_to,
            'skipped_fields' => array_values( array_unique( $skipped ) ),
            'skipped_by'     => $skipped_by,
        );
    }

    /**
     * @return array{code: string, message: string, rejected_fields: string[]}
     */
    protected function empty_data_error( SeoData $data ): array {
        $rejected = $data->rejected_fields();

        if ( empty( $rejected ) ) {
            return array(
                'code'            => 'seo_no_fields',
                'message'         => __( 'Provide at least one SEO field to update.', 'hostinger-ai-assistant' ),
                'rejected_fields' => array(),
            );
        }

        return array(
            'code'            => 'seo_all_fields_rejected',
            'message'         => sprintf(
                /* translators: %s: comma-separated list of field names */
                __( 'None of the provided values could be used: %s. Check that URLs are absolute and use http or https, and that robots directives are true, false or "default".', 'hostinger-ai-assistant' ),
                implode( ', ', $rejected )
            ),
            'rejected_fields' => $rejected,
        );
    }

    protected function can_edit( $post_id ): bool {
        if ( ! is_numeric( $post_id ) ) {
            return false;
        }

        $post_id = absint( $post_id );

        if ( ! $post_id ) {
            return false;
        }

        return current_user_can( 'edit_post', $post_id );
    }

    protected function provider_meta(): array {
        $active = $this->resolver->get_active_keys();

        $meta = array(
            'provider'         => $this->resolver->get_primary()->get_key(),
            'active_providers' => $active,
            'supported_fields' => $this->resolver->get_supported_fields(),
        );

        if ( count( $active ) > 1 ) {
            $meta['warning'] = __( 'Several SEO plugins are active on this site. Values are read from the primary provider, but All in One SEO takes precedence in the rendered page when it is active, so the reported values may not be the ones visitors see. Consider keeping a single SEO plugin.', 'hostinger-ai-assistant' );
        }

        return $meta;
    }

    protected function ability_meta( array $annotations ): array {
        return array(
            'show_in_rest' => true,
            'mcp'          => array(
                'public' => true,
                'type'   => $this->type,
            ),
            'annotations'  => $annotations,
        );
    }
}
