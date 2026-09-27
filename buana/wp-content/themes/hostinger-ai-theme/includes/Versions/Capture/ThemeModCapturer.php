<?php

namespace Hostinger\AiTheme\Versions\Capture;

use Hostinger\AiTheme\Versions\VersionConstant;
use Hostinger\AiTheme\Versions\VersionRepository;
use Hostinger\AiTheme\Versions\VersionSubject;

defined( 'ABSPATH' ) || exit;

class ThemeModCapturer {
    private VersionSubject $subject;
    private VersionRepository $repository;

    public function __construct( VersionSubject $subject, VersionRepository $repository ) {
        $this->subject    = $subject;
        $this->repository = $repository;
    }

    public function capture( int $version_id ): int {
        $rows = array();

        foreach ( $this->subject->get_theme_mods() as $key => $value ) {
            $rows[] = array(
                'version_id' => $version_id,
                'mod_key'    => (string) $key,
                'mod_value'  => maybe_serialize( $value ),
            );
        }

        return $this->repository->insert_rows( VersionConstant::TABLE_THEME_MODS, $rows );
    }
}
