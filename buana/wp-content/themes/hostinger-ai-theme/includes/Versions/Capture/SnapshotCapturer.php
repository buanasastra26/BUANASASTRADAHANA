<?php

namespace Hostinger\AiTheme\Versions\Capture;

use Hostinger\AiTheme\Data\WebsiteTypeHelper;
use Hostinger\AiTheme\Versions\VersionRepository;
use Hostinger\AiTheme\Versions\VersionSubject;

defined( 'ABSPATH' ) || exit;

class SnapshotCapturer {
    private VersionSubject $subject;
    private VersionRepository $repository;

    public function __construct( ?VersionSubject $subject = null, ?VersionRepository $repository = null ) {
        $this->subject    = $subject ?? new VersionSubject();
        $this->repository = $repository ?? new VersionRepository();
    }

    public function capture(): int {
        $version_id = $this->repository->insert_version( $this->build_version_row() );
        if ( $version_id <= 0 ) {
            return 0;
        }

        $post_ids = ( new PostCapturer( $this->subject, $this->repository ) )->capture( $version_id );

        ( new OptionCapturer( $this->subject, $this->repository ) )->capture( $version_id );
        ( new TermCapturer( $this->subject, $this->repository ) )->capture( $version_id, $post_ids );
        ( new ThemeModCapturer( $this->subject, $this->repository ) )->capture( $version_id );
        ( new PluginStateCapturer( $this->subject, $this->repository ) )->capture( $version_id );

        $this->backfill_metrics( $version_id );

        return $version_id;
    }

    private function build_version_row(): array {
        $brand_name = (string) get_option( 'hostinger_ai_brand_name', '' );
        $blogname   = (string) get_option( 'blogname', '' );

        return array(
            'label'         => substr( $brand_name !== '' ? $brand_name : $blogname, 0, 191 ),
            'brand_name'    => substr( $brand_name, 0, 191 ),
            'description'   => (string) get_option( 'hostinger_ai_description', '' ),
            'builder_type'  => substr( (string) get_option( 'hostinger_ai_builder_type', '' ), 0, 32 ),
            'website_type'  => substr( implode( ', ', WebsiteTypeHelper::get_website_types() ), 0, 191 ),
            'locale'        => substr( (string) get_option( 'hostinger_ai_selected_language', get_locale() ), 0, 20 ),
            'front_page_id' => (int) get_option( 'page_on_front', 0 ),
            'theme_version' => substr( (string) wp_get_theme()->get( 'Version' ), 0, 20 ),
        );
    }

    private function backfill_metrics( int $version_id ): void {
        $counts = $this->repository->count_posts_by_type( $version_id );

        $this->repository->update_version(
            $version_id,
            array(
                'page_count'    => $counts['page'] ?? 0,
                'post_count'    => $counts['post'] ?? 0,
                'product_count' => $counts['product'] ?? 0,
                'size_bytes'    => $this->repository->calculate_size_bytes( $version_id ),
            )
        );
    }
}
