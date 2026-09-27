<?php

namespace Hostinger\AiTheme\Versions;

defined( 'ABSPATH' ) || exit;

class VersionConstant {
    public const DB_VERSION = 2;
    public const SCHEMA_VERSION = 1;
    public const DB_VERSION_OPTION = 'hostinger_ai_versions_db_version';
    public const RESTORE_IN_PROGRESS_OPTION = 'hostinger_ai_restore_in_progress';
    public const MAX_VERSIONS = 5;

    /**
     * Table suffixes, appended to $wpdb->prefix.
     */
    public const TABLE_VERSIONS            = 'hostinger_ai_versions';
    public const TABLE_POSTS               = 'hostinger_ai_version_posts';
    public const TABLE_POSTMETA            = 'hostinger_ai_version_postmeta';
    public const TABLE_OPTIONS             = 'hostinger_ai_version_options';
    public const TABLE_TERMS               = 'hostinger_ai_version_terms';
    public const TABLE_TERM_RELATIONSHIPS  = 'hostinger_ai_version_term_relationships';
    public const TABLE_THEME_MODS          = 'hostinger_ai_version_theme_mods';
    public const TABLE_PLUGINS             = 'hostinger_ai_version_plugins';

    public const TABLES = array(
        self::TABLE_VERSIONS,
        self::TABLE_POSTS,
        self::TABLE_POSTMETA,
        self::TABLE_OPTIONS,
        self::TABLE_TERMS,
        self::TABLE_TERM_RELATIONSHIPS,
        self::TABLE_THEME_MODS,
        self::TABLE_PLUGINS,
    );

    /**
     * Every table except the parent, keyed by version_id. Used for cascading deletes.
     */
    public const CHILD_TABLES = array(
        self::TABLE_POSTS,
        self::TABLE_POSTMETA,
        self::TABLE_OPTIONS,
        self::TABLE_TERMS,
        self::TABLE_TERM_RELATIONSHIPS,
        self::TABLE_THEME_MODS,
        self::TABLE_PLUGINS,
    );

    /**
     * Roles select the restore resolution strategy (see ContentResolver).
     */
    public const ROLE_GENERATED     = 'generated';
    public const ROLE_TEMPLATE_PART = 'template_part';
    public const ROLE_TEMPLATE      = 'template';
    public const ROLE_KIT           = 'kit';

    /**
     * Rows per multi-row INSERT during capture.
     */
    public const INSERT_BATCH_SIZE = 100;

    /**
     * Screenshot lifecycle, stored on the parent version row.
     */
    public const SCREENSHOT_QUEUED  = 'queued';
    public const SCREENSHOT_PENDING = 'pending';
    public const SCREENSHOT_READY   = 'ready';
    public const SCREENSHOT_FAILED  = 'failed';

    /**
     * Directory under wp-content/uploads holding the pinned screenshots.
     */
    public const SCREENSHOT_DIR = 'hostinger-ai-versions';
}
