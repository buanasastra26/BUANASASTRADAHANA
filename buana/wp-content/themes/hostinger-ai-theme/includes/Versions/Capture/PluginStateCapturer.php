<?php

namespace Hostinger\AiTheme\Versions\Capture;

use Hostinger\AiTheme\Versions\VersionConstant;
use Hostinger\AiTheme\Versions\VersionRepository;
use Hostinger\AiTheme\Versions\VersionSubject;

defined( 'ABSPATH' ) || exit;

class PluginStateCapturer {
    private VersionSubject $subject;
    private VersionRepository $repository;

    public function __construct( VersionSubject $subject, VersionRepository $repository ) {
        $this->subject    = $subject;
        $this->repository = $repository;
    }

    public function capture( int $version_id ): int {
        $active = $this->subject->get_active_plugins();
        $known  = $this->subject->get_known_plugins();

        $plugin_files = array_values( array_unique( array_merge( $active, $known ) ) );

        $rows = array();
        foreach ( $plugin_files as $plugin_file ) {
            $rows[] = array(
                'version_id'     => $version_id,
                'plugin_file'    => (string) $plugin_file,
                'is_active'      => in_array( $plugin_file, $active, true ) ? 1 : 0,
                'plugin_version' => $this->get_plugin_version( (string) $plugin_file ),
            );
        }

        return $this->repository->insert_rows( VersionConstant::TABLE_PLUGINS, $rows );
    }

    private function get_plugin_version( string $plugin_file ): string {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $path = WP_PLUGIN_DIR . '/' . $plugin_file;

        if ( ! file_exists( $path ) ) {
            return '';
        }

        $data = get_plugin_data( $path, false, false );

        return substr( (string) ( $data['Version'] ?? '' ), 0, 32 );
    }
}
