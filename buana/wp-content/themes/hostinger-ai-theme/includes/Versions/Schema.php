<?php

namespace Hostinger\AiTheme\Versions;

defined( 'ABSPATH' ) || exit;

class Schema {
    public static function maybe_install(): void {
        if ( (int) get_option( VersionConstant::DB_VERSION_OPTION, 0 ) === VersionConstant::DB_VERSION ) {
            return;
        }

        self::install();
    }

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        foreach ( self::get_table_definitions( $charset_collate ) as $sql ) {
            dbDelta( $sql );
        }

        update_option( VersionConstant::DB_VERSION_OPTION, VersionConstant::DB_VERSION, false );
    }

    public static function table( string $suffix ): string {
        global $wpdb;

        return $wpdb->prefix . $suffix;
    }

    public static function is_installed(): bool {
        global $wpdb;

        foreach ( VersionConstant::TABLES as $suffix ) {
            $table = self::table( $suffix );

            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
                return false;
            }
        }

        return true;
    }

    private static function get_table_definitions( string $charset_collate ): array {
        $versions            = self::table( VersionConstant::TABLE_VERSIONS );
        $posts               = self::table( VersionConstant::TABLE_POSTS );
        $postmeta            = self::table( VersionConstant::TABLE_POSTMETA );
        $options             = self::table( VersionConstant::TABLE_OPTIONS );
        $terms               = self::table( VersionConstant::TABLE_TERMS );
        $term_relationships  = self::table( VersionConstant::TABLE_TERM_RELATIONSHIPS );
        $theme_mods          = self::table( VersionConstant::TABLE_THEME_MODS );
        $plugins             = self::table( VersionConstant::TABLE_PLUGINS );

        return array(
            "CREATE TABLE {$versions} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sequence int(10) unsigned NOT NULL DEFAULT 0,
  label varchar(191) NOT NULL DEFAULT '',
  brand_name varchar(191) NOT NULL DEFAULT '',
  description text NULL,
  builder_type varchar(32) NOT NULL DEFAULT '',
  website_type varchar(191) NOT NULL DEFAULT '',
  locale varchar(20) NOT NULL DEFAULT '',
  front_page_id bigint(20) unsigned NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 0,
  schema_version smallint(5) unsigned NOT NULL DEFAULT 1,
  theme_version varchar(20) NOT NULL DEFAULT '',
  page_count smallint(5) unsigned NOT NULL DEFAULT 0,
  post_count smallint(5) unsigned NOT NULL DEFAULT 0,
  product_count smallint(5) unsigned NOT NULL DEFAULT 0,
  size_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
  screenshot_status varchar(20) NOT NULL DEFAULT '',
  screenshot_file varchar(191) NOT NULL DEFAULT '',
  screenshot_hash varchar(40) NOT NULL DEFAULT '',
  screenshot_requested_at datetime NULL,
  screenshot_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY is_active (is_active),
  KEY created_at (created_at)
) {$charset_collate};",

            "CREATE TABLE {$posts} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  version_id bigint(20) unsigned NOT NULL,
  post_id bigint(20) unsigned NOT NULL,
  role varchar(32) NOT NULL DEFAULT 'generated',
  post_author bigint(20) unsigned NOT NULL DEFAULT 0,
  post_date datetime NOT NULL,
  post_date_gmt datetime NOT NULL,
  post_content longtext NOT NULL,
  post_title text NOT NULL,
  post_excerpt text NOT NULL,
  post_status varchar(20) NOT NULL DEFAULT 'publish',
  comment_status varchar(20) NOT NULL DEFAULT 'closed',
  ping_status varchar(20) NOT NULL DEFAULT 'closed',
  post_password varchar(255) NOT NULL DEFAULT '',
  post_name varchar(200) NOT NULL DEFAULT '',
  to_ping text NOT NULL,
  pinged text NOT NULL,
  post_modified datetime NOT NULL,
  post_modified_gmt datetime NOT NULL,
  post_content_filtered longtext NOT NULL,
  post_parent bigint(20) unsigned NOT NULL DEFAULT 0,
  guid varchar(255) NOT NULL DEFAULT '',
  menu_order int(11) NOT NULL DEFAULT 0,
  post_type varchar(20) NOT NULL DEFAULT 'post',
  post_mime_type varchar(100) NOT NULL DEFAULT '',
  comment_count bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY version_post (version_id,post_id),
  KEY version_type (version_id,post_type)
) {$charset_collate};",

            "CREATE TABLE {$postmeta} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  version_id bigint(20) unsigned NOT NULL,
  post_id bigint(20) unsigned NOT NULL,
  meta_key varchar(255) NULL,
  meta_value longtext NULL,
  PRIMARY KEY  (id),
  KEY version_post (version_id,post_id),
  KEY version_key (version_id,meta_key(191))
) {$charset_collate};",

            "CREATE TABLE {$options} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  version_id bigint(20) unsigned NOT NULL,
  option_name varchar(191) NOT NULL,
  option_value longtext NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY version_option (version_id,option_name)
) {$charset_collate};",

            "CREATE TABLE {$terms} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  version_id bigint(20) unsigned NOT NULL,
  term_id bigint(20) unsigned NOT NULL,
  name varchar(200) NOT NULL DEFAULT '',
  slug varchar(200) NOT NULL DEFAULT '',
  term_group bigint(10) NOT NULL DEFAULT 0,
  taxonomy varchar(32) NOT NULL DEFAULT '',
  description longtext NULL,
  parent bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY version_term (version_id,term_id,taxonomy),
  KEY version_slug (version_id,taxonomy,slug)
) {$charset_collate};",

            "CREATE TABLE {$term_relationships} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  version_id bigint(20) unsigned NOT NULL,
  object_id bigint(20) unsigned NOT NULL,
  term_id bigint(20) unsigned NOT NULL,
  taxonomy varchar(32) NOT NULL DEFAULT '',
  term_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY version_object (version_id,object_id)
) {$charset_collate};",

            "CREATE TABLE {$theme_mods} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  version_id bigint(20) unsigned NOT NULL,
  mod_key varchar(191) NOT NULL,
  mod_value longtext NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY version_mod (version_id,mod_key)
) {$charset_collate};",

            "CREATE TABLE {$plugins} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  version_id bigint(20) unsigned NOT NULL,
  plugin_file varchar(255) NOT NULL,
  is_active tinyint(1) NOT NULL DEFAULT 0,
  plugin_version varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY version_plugin (version_id)
) {$charset_collate};",
        );
    }
}
