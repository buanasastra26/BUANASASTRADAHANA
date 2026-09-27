<?php

namespace Hostinger\AiTheme\Builder\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Every opaque identifier the Elementor kit stores in `_elementor_page_settings`.
 *
 * Elementor keys its global colours and typography entries by generated ids. They
 * live on published kits and inside authored templates, so they cannot be renamed
 * or regenerated -- they are collected here so no literal is repeated in logic.
 */
class KitIds {
    // Global typography entries.
    public const H1_TYPOGRAPHY         = '5535e8e';
    public const H2_TYPOGRAPHY         = 'c83476d';
    public const H1_REGULAR_TYPOGRAPHY = 'ff8f921';
    public const BODY_TYPOGRAPHY       = '887fca2';
    public const BODY_BOLD_TYPOGRAPHY  = '2142591';
    public const BUTTON_TYPOGRAPHY     = '6e52843';

    public const HEADING_TYPOGRAPHY = array(
        self::H1_TYPOGRAPHY,
        self::H2_TYPOGRAPHY,
        self::H1_REGULAR_TYPOGRAPHY,
    );
    public const BODY_TYPOGRAPHY_GROUP = array(
        self::BODY_TYPOGRAPHY,
        self::BODY_BOLD_TYPOGRAPHY,
        self::BUTTON_TYPOGRAPHY,
    );

    // Font-family globals the v2 flow adds on top of the authored entries.
    public const V2_HEADING_FONT = 'v2hfont';
    public const V2_BODY_FONT    = 'v2bfont';
    public const V2_BUTTON_FONT  = 'v2btnft';

    // Global colours.
    public const COLOR_1          = 'b5aeb33';
    public const COLOR_2          = 'c58817e';
    public const COLOR_3          = '5420d44';
    public const LIGHT            = '58be983';
    public const DARK             = '09cc561';
    public const GREY             = 'a495fd4';
    public const GRADIENT_COLOR_1 = 'dff8941';
    public const PAGE_BACKGROUND  = 'a7b8c9d';
    public const PAGE_TEXT        = 'b1c2d3e';
    public const SURFACE_ALT      = 'e3ded6a';
    public const PRIMARY_SOFT     = 'd5ac99a';
    public const ACCENT           = '8a9e7e1';
    public const ACCENT_SOFT      = 'c4cbb8a';

    // Palette slug => global colour id.
    public const TOKEN_COLORS = array(
        'color1'       => self::COLOR_1,
        'color2'       => self::COLOR_2,
        'color3'       => self::COLOR_3,
        'light'        => self::LIGHT,
        'dark'         => self::DARK,
        'grey'         => self::GREY,
        'base'         => self::PAGE_BACKGROUND,
        'surface'      => self::COLOR_1,
        'surface-alt'  => self::SURFACE_ALT,
        'ink'          => self::PAGE_TEXT,
        'contrast'     => self::PAGE_TEXT,
        'ink-soft'     => self::GREY,
        'ink-inverse'  => self::LIGHT,
        'primary'      => self::COLOR_3,
        'primary-soft' => self::PRIMARY_SOFT,
        'accent'       => self::ACCENT,
        'accent-soft'  => self::ACCENT_SOFT,
    );

    // Kit settings keys.
    public const CUSTOM_TYPOGRAPHY_KEY = 'custom_typography';
    public const CUSTOM_COLORS_KEY     = 'custom_colors';
}
