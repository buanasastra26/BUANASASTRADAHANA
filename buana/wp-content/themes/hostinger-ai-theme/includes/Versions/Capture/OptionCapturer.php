<?php

namespace Hostinger\AiTheme\Versions\Capture;

use Hostinger\AiTheme\Versions\VersionConstant;
use Hostinger\AiTheme\Versions\VersionRepository;
use Hostinger\AiTheme\Versions\VersionSubject;

defined( 'ABSPATH' ) || exit;

class OptionCapturer {
    private VersionSubject $subject;
    private VersionRepository $repository;

    public function __construct( VersionSubject $subject, VersionRepository $repository ) {
        $this->subject    = $subject;
        $this->repository = $repository;
    }

    public function capture( int $version_id ): int {
        global $wpdb;

        $names = $this->subject->get_option_names();
        if ( empty( $names ) ) {
            return 0;
        }

        $rows = array();
        foreach ( array_chunk( $names, VersionConstant::INSERT_BATCH_SIZE ) as $chunk ) {
            $placeholders = implode( ', ', array_fill( 0, count( $chunk ), '%s' ) );

            $options = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ({$placeholders})",
                    $chunk
                ),
                ARRAY_A
            );

            foreach ( $options as $option ) {
                $rows[] = array(
                    'version_id'   => $version_id,
                    'option_name'  => $option['option_name'],
                    'option_value' => (string) $option['option_value'],
                );
            }
        }

        return $this->repository->insert_rows( VersionConstant::TABLE_OPTIONS, $rows );
    }
}
