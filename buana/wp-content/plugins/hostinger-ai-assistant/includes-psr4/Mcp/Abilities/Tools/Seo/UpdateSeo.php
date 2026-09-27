<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class UpdateSeo extends BaseSeoTool {
    public function register(): void {
        $properties = array_merge(
            array(
                'id' => array(
                    'type'        => 'integer',
                    'description' => __( 'ID of the post or page.', 'hostinger-ai-assistant' ),
                ),
            ),
            $this->seo_field_properties()
        );

        wp_register_ability(
            'hostinger-ai/seo-update',
            array(
                'label'               => __( 'Update SEO Metadata', 'hostinger-ai-assistant' ),
                'description'         => __( 'Change the SEO metadata of one post or page. Use this whenever asked to set or improve the SEO of a specific page: "write a better meta description for this page", "shorten the SEO title", "optimise this post for <keyword>", "hide this page from search engines", "stop Google indexing this", "set the canonical URL", "set the social share title or image". Accepts the meta title, meta description, canonical URL, robots directives (noindex, nofollow, noarchive), focus keyword and social preview (Open Graph and X/Twitter) fields. For more than one post in a single request use seo-bulk-update instead. Only the fields you pass are changed; pass an empty string to clear a field. Each robots directive is tri-state: true turns the directive on, false pins it off, and "default" removes the per-post override so the site-wide setting applies again. Writes to every active SEO plugin (Yoast SEO, Rank Math, All in One SEO), or to Hostinger storage when no SEO plugin is installed. "written_to" lists the providers that stored something, "skipped_fields" lists every field that was refused, and "skipped_by" says which provider refused each one ("input" means the value itself was invalid). When only one provider is listed in "active_providers", a skipped field was not stored at all; when several are, it may still be live on another. Values are read back from the primary provider only, so a field you cleared and a field the primary does not support both come back absent — use "skipped_by" to tell them apart.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => $properties,
                    'required'   => array( 'id' ),
                ),
                'execute_callback'    => array( $this, 'execute' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'meta'                => $this->ability_meta(
                    array(
                        'title'           => 'Update SEO Metadata',
                        'readOnlyHint'    => false,
                        'destructiveHint' => false,
                        'idempotentHint'  => true,
                        'openWorldHint'   => false,
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

        $data = SeoData::from_array( $input );

        if ( $data->is_empty() ) {
            $error = $this->empty_data_error( $data );

            return new WP_Error(
                $error['code'],
                $error['message'],
                array(
                    'status'          => 400,
                    'rejected_fields' => $error['rejected_fields'],
                )
            );
        }

        $result = $this->write( $post->ID, $data );

        if ( empty( $result['written_to'] ) ) {
            return new WP_Error(
                'seo_nothing_written',
                __( 'None of the requested fields could be stored by any active SEO provider.', 'hostinger-ai-assistant' ),
                array(
                    'status'         => 422,
                    'skipped_fields' => $result['skipped_fields'],
                    'skipped_by'     => $result['skipped_by'],
                )
            );
        }

        return array_merge(
            array(
                'success'        => true,
                'id'             => $post->ID,
                'written_to'     => $result['written_to'],
                'skipped_fields' => $result['skipped_fields'],
                'skipped_by'     => $result['skipped_by'],
            ),
            $this->resolver->get_primary()->read( $post->ID )->to_array(),
            $this->provider_meta()
        );
    }
}
