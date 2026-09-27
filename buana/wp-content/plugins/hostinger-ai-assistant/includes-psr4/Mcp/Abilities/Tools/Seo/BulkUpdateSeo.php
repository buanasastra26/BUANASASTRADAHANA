<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Seo;

use Throwable;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class BulkUpdateSeo extends BaseSeoTool {
    public const MAX_POSTS = 50;

    public function register(): void {
        $entry_properties = array_merge(
            array(
                'id' => array(
                    'type'        => 'integer',
                    'description' => __( 'ID of the post or page.', 'hostinger-ai-assistant' ),
                ),
            ),
            $this->seo_field_properties()
        );

        wp_register_ability(
            'hostinger-ai/seo-bulk-update',
            array(
                'label'               => __( 'Bulk Update SEO Metadata', 'hostinger-ai-assistant' ),
                'description'         => sprintf(
                    /* translators: %d: maximum number of posts per call */
                    __( 'Change SEO metadata on several posts or pages in one call, up to %d per call. Use this for anything phrased across multiple pages: "fix the missing meta descriptions", "shorten every title that is too long", "optimise the SEO of my blog posts", "noindex these pages". Pair it with seo-audit, which finds the posts that need fixing; for a single page use seo-update. Each entry needs an id plus the SEO fields to change; only the fields you pass are changed. Entries are processed independently, so one failure does not stop the rest — the response lists updated entries and failed entries with their reasons. An entry no provider could store anything for is reported as failed with the code seo_nothing_written, and "success" is true only when no entry failed and at least one entry actually wrote something. The output of seo-audit can be passed straight in as the posts array.', 'hostinger-ai-assistant' ),
                    self::MAX_POSTS
                ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'posts' => array(
                            'type'        => 'array',
                            'description' => __( 'Posts to update, each with an id and the SEO fields to change.', 'hostinger-ai-assistant' ),
                            'maxItems'    => self::MAX_POSTS,
                            'items'       => array(
                                'type'       => 'object',
                                'properties' => $entry_properties,
                                'required'   => array( 'id' ),
                            ),
                        ),
                    ),
                    'required'   => array( 'posts' ),
                ),
                'execute_callback'    => array( $this, 'execute' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'meta'                => $this->ability_meta(
                    array(
                        'title'           => 'Bulk Update SEO Metadata',
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
        $entries = $input['posts'] ?? array();

        if ( ! is_array( $entries ) || empty( $entries ) ) {
            return false;
        }

        foreach ( $entries as $entry ) {
            if ( is_array( $entry ) && $this->can_edit( $entry['id'] ?? 0 ) ) {
                return true;
            }
        }

        return false;
    }

    public function execute( array $input ): WP_Error|array {
        $entries = $input['posts'] ?? array();

        if ( ! is_array( $entries ) || empty( $entries ) ) {
            return new WP_Error(
                'seo_bulk_empty',
                __( 'Provide at least one post to update.', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        if ( count( $entries ) > self::MAX_POSTS ) {
            return new WP_Error(
                'seo_bulk_limit_exceeded',
                sprintf(
                    /* translators: %d: maximum number of posts per call */
                    __( 'A bulk update accepts at most %d posts per call. Split the work into smaller batches.', 'hostinger-ai-assistant' ),
                    self::MAX_POSTS
                ),
                array( 'status' => 400 )
            );
        }

        $updated    = array();
        $failed     = array();
        $written_to = array();

        foreach ( $entries as $entry ) {
            $outcome = $this->update_entry( is_array( $entry ) ? $entry : array() );

            if ( isset( $outcome['error'] ) ) {
                $failed[] = $outcome['error'];
                continue;
            }

            $updated[]  = $outcome['updated'];
            $written_to = array_merge( $written_to, $outcome['written_to'] );
        }

        return array_merge(
            array(
                'success'    => empty( $failed ) && ! empty( $updated ),
                'updated'    => $updated,
                'failed'     => $failed,
                'written_to' => array_values( array_unique( $written_to ) ),
            ),
            $this->provider_meta()
        );
    }

    private function update_entry( array $entry ): array {
        $post_id = absint( $entry['id'] ?? 0 );
        $post    = $this->resolve_post( $post_id );

        if ( is_wp_error( $post ) ) {
            return array(
                'error' => array(
                    'id'      => $entry['id'] ?? 0,
                    'code'    => $post->get_error_code(),
                    'message' => $post->get_error_message(),
                ),
            );
        }

        if ( ! $this->can_edit( $post->ID ) ) {
            return array(
                'error' => array(
                    'id'      => $post->ID,
                    'code'    => 'seo_forbidden',
                    'message' => __( 'You are not allowed to edit this post.', 'hostinger-ai-assistant' ),
                ),
            );
        }

        $data = SeoData::from_array( $entry );

        if ( $data->is_empty() ) {
            $error = $this->empty_data_error( $data );

            return array(
                'error' => array(
                    'id'              => $post->ID,
                    'code'            => $error['code'],
                    'message'         => $error['message'],
                    'rejected_fields' => $error['rejected_fields'],
                ),
            );
        }

        try {
            $result = $this->write( $post->ID, $data );
        } catch ( Throwable $e ) {
            return array(
                'error' => array(
                    'id'      => $post->ID,
                    'code'    => 'seo_write_failed',
                    'message' => $e->getMessage(),
                ),
            );
        }

        if ( empty( $result['written_to'] ) ) {
            return array(
                'error' => array(
                    'id'             => $post->ID,
                    'code'           => 'seo_nothing_written',
                    'message'        => __( 'None of the requested fields could be stored by any active SEO provider.', 'hostinger-ai-assistant' ),
                    'skipped_fields' => $result['skipped_fields'],
                    'skipped_by'     => $result['skipped_by'],
                ),
            );
        }

        return array(
            'updated'    => array(
                'id'             => $post->ID,
                'fields'         => $data->fields(),
                'skipped_fields' => $result['skipped_fields'],
                'skipped_by'     => $result['skipped_by'],
            ),
            'written_to' => $result['written_to'],
        );
    }
}
