<?php

namespace Hostinger\AiTheme\Admin;

defined( 'ABSPATH' ) || exit;

class GutenbergPreferences {
    public const PERSISTED_PREFERENCES_META_KEY = 'wp_persisted_preferences';
    public const EDIT_SITE_KEY                  = 'core/edit-site';
    public const WELCOME_GUIDE_KEY              = 'welcomeGuide';
    public const WELCOME_GUIDE_PAGE_KEY         = 'welcomeGuidePage';

    public function disable_welcome_guide(): void {
        $admin_users = get_users( array( 'role' => 'administrator' ) );

        foreach ( $admin_users as $user ) {
            if ( $this->welcome_guide_already_disabled( $user->ID ) ) {
                continue;
            }

            $this->update_user_preferences( $user->ID );
        }
    }

    public function welcome_guide_already_disabled( int $user_id ): bool {
        $existing  = get_user_meta( $user_id, self::PERSISTED_PREFERENCES_META_KEY, true );
        $edit_site = $existing[ self::EDIT_SITE_KEY ] ?? array();

        return ( $edit_site[ self::WELCOME_GUIDE_KEY ] ?? true ) === false
            && ( $edit_site[ self::WELCOME_GUIDE_PAGE_KEY ] ?? true ) === false;
    }

    public function update_user_preferences( int $user_id ): void {
        $existing = get_user_meta( $user_id, self::PERSISTED_PREFERENCES_META_KEY, true );

        if ( ! is_array( $existing ) ) {
            $existing = array();
        }

        $core_prefs = isset( $existing['core'] ) && is_array( $existing['core'] ) ? $existing['core'] : array();

        $preferences = array_merge(
            $existing,
            array(
                'core'              => $core_prefs,
                '_modified'         => gmdate( 'Y-m-d\TH:i:s.v\Z' ),
                self::EDIT_SITE_KEY => array(
                    self::WELCOME_GUIDE_KEY      => false,
                    self::WELCOME_GUIDE_PAGE_KEY => false,
                ),
            )
        );

        update_user_meta( $user_id, self::PERSISTED_PREFERENCES_META_KEY, $preferences );
    }
}
