<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Constants\GenerationConstant;
use WP_Query;

defined( 'ABSPATH' ) || exit;

class GenerationCleaner {
    public function sweep(): int {
        $deleted = $this->delete_tagged_content();

        $this->clear_woo_page_options();
        ( new AffiliateBuilder() )->clear_catalog();

        return $deleted;
    }

    private function delete_tagged_content(): int {
        $query = new WP_Query(
            array(
                'post_type'      => GenerationConstant::GENERATED_POST_TYPES,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => GenerationConstant::META_KEY,
                'no_found_rows'  => true,
                'cache_results'  => false,
            )
        );

        $deleted = 0;

        foreach ( $query->posts as $post_id ) {
            $result = wp_delete_post( (int) $post_id, true );

            if ( ! $result ) {
                error_log( 'Hostinger AI Theme: Failed to delete generated content ' . $post_id );
                continue;
            }

            $deleted++;
        }

        return $deleted;
    }

    private function clear_woo_page_options(): void {
        foreach ( GenerationConstant::WOO_PAGE_OPTIONS as $option_name ) {
            delete_option( $option_name );
        }
    }
}
