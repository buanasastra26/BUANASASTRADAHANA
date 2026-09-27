<?php

namespace Hostinger\AiTheme\Builder;

use Automatic_Upgrader_Skin;
use Elementor\Plugin as ElementorPlugin;
use Hostinger\AiTheme\Versions\Schema;
use Hostinger\AiTheme\Versions\VersionConstant;
use Hostinger\AiTheme\Versions\VersionRepository;
use Language_Pack_Upgrader;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class Helper {

	const HOSTINGER_AI_THEME_GENERATED_ONCE_OPTION = 'hostinger_ai_site_generated_once';

    public const LITESPEED_PLUGIN = 'litespeed-cache/litespeed-cache.php';

    private const LOG_PREFIX = '[hostinger-ai-theme] ';

    private static ?int $website_versions_count = null;

    public static function is_site_generated(): bool {
        if ( ! empty( get_option( self::HOSTINGER_AI_THEME_GENERATED_ONCE_OPTION, false ) ) ) {
            return true;
        }

        return self::website_versions_count() > 0;
    }

    public static function website_versions_count(): int {
        if ( self::$website_versions_count !== null ) {
            return self::$website_versions_count;
        }

        self::$website_versions_count = self::versions_schema_ready() ? ( new VersionRepository() )->count_versions() : 0;

        return self::$website_versions_count;
    }

    public static function has_finished_builder_onboarding(): bool {
        return self::is_site_generated() || ! empty( get_option( 'hostinger_ai_version', false ) );
    }

    public static function reset_website_versions_count_cache(): void {
        self::$website_versions_count = null;
    }

    private static function versions_schema_ready(): bool {
        if ( (int) get_option( VersionConstant::DB_VERSION_OPTION, 0 ) === VersionConstant::DB_VERSION ) {
            return true;
        }

        return Schema::is_installed();
    }

    public static function log( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( self::LOG_PREFIX . $message );
        }
    }

    // Slash first: wp_insert_post unslashes, which strips `\u002d\u002d` from `--` in CSS vars.
    // Disable KSES for the insert: without unfiltered_html (WP-CLI) it strips block attrs.
    public static function insert_trusted_post( array $postarr ): int|WP_Error {
        kses_remove_filters();

        try {
            return wp_insert_post( wp_slash( $postarr ) );
        } finally {
            kses_init();
        }
    }

    public static function is_elementor_active(): bool {
        return defined( 'ELEMENTOR_VERSION' ) || class_exists( ElementorPlugin::class );
    }

    public static function oldest_administrator_id(): int {
        $administrator_ids = get_users(
            array(
                'role'    => 'administrator',
                'number'  => 1,
                'orderby' => 'ID',
                'order'   => 'ASC',
                'fields'  => 'ID',
            )
        );

        return ! empty( $administrator_ids ) ? (int) $administrator_ids[0] : 0;
    }

    /**
     * @param array $structure
     * @param array $element_data
     *
     * @return false|mixed
     */
    public function find_structure( array $structure, array $element_data ): mixed
    {
        foreach($structure as $index => $data) {
            if($data['class'] == $element_data['class'] && $data['index'] == $element_data['index']) {
                return $data;
            }
        }

        return array();
    }

    /**
     * @param string $string
     * @param string $pattern
     *
     * @return array
     */
    public function extract_class_names( string $string, string $pattern ): array {
        preg_match_all( $pattern, $string, $matches );
        return $matches[0];
    }

    /**
     * @param string $string
     *
     * @return int
     */
    public function extract_index_number( string $string ): int
    {
        $pattern = '/hostinger-index-(\d+)/';

        if (preg_match($pattern, $string, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    public static function get_elementor_version(): string {

        if ( defined('ELEMENTOR_VERSION') ) {
            return ELEMENTOR_VERSION;
        }

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = WP_PLUGIN_DIR . '/elementor/elementor.php';

        if ( file_exists( $plugin_file ) ) {
            $plugin_data = get_plugin_data( $plugin_file, false, false );
            if ( ! empty( $plugin_data['Version'] ) ) {
                return $plugin_data['Version'];
            }
        }

        return '3.31.3';
    }

    public static function save_elementor_data( int $page_id, string $elementor_json ): void {
        update_post_meta( $page_id, '_elementor_data', wp_slash( $elementor_json ) );
    }

    public static function get_locale_name( string $wp_locale ): string {
        $locale_map = self::get_locale_mapping();

        return $locale_map[ $wp_locale ] ?? 'English';
    }

	public static function get_site_locale(): string {
		$wp_locale = get_option( 'hostinger_ai_selected_language', 'en_US' );

		return self::get_locale_name( $wp_locale );
	}

    public static function switch_to_selected_locale(): bool {
		$locale = (string) get_option( 'hostinger_ai_selected_language', 'en_US' );

		if ( $locale === '' || $locale === 'en_US' ) {
			return false;
		}

		$switched = switch_to_locale( $locale );
		self::reload_theme_textdomain( $locale );

		return $switched;
	}

	public static function restore_selected_locale( bool $switched ): void {
		if ( ! $switched ) {
			return;
		}

		restore_previous_locale();
		self::reload_theme_textdomain( determine_locale() );
	}

	private static function reload_theme_textdomain( string $locale ): void {
		$mo_file = get_template_directory() . '/languages/hostinger-ai-theme-' . $locale . '.mo';

		if ( ! file_exists( $mo_file ) ) {
			return;
		}

		unload_textdomain( 'hostinger-ai-theme' );
		load_textdomain( 'hostinger-ai-theme', $mo_file, $locale );
	}

	public static function should_render_india_version(): bool {
		$locale = self::get_site_locale();
		$is_first_generation = ! get_option( self::HOSTINGER_AI_THEME_GENERATED_ONCE_OPTION, false );
		return in_array( $locale, array( 'Hindi') ) && $is_first_generation;
	}

    public static function get_locale_mapping(): array {
        return array(
            'af' => 'Afrikaans',
            'ak' => 'Akan',
            'sq' => 'Albanian',
            'arq' => 'Algerian Arabic',
            'am' => 'Amharic',
            'ar' => 'Arabic',
            'hy' => 'Armenian',
            'rup_MK' => 'Aromanian',
            'frp' => 'Arpitan',
            'as' => 'Assamese',
            'az' => 'Azerbaijani',
            'az_TR' => 'Azerbaijani (Turkey)',
            'bcc' => 'Balochi Southern',
            'ba' => 'Bashkir',
            'eu' => 'Basque',
            'bel' => 'Belarusian',
            'bn_BD' => 'Bengali',
            'bs_BA' => 'Bosnian',
            'bre' => 'Breton',
            'bg_BG' => 'Bulgarian',
            'ca' => 'Catalan',
            'bal' => 'Catalan (Balear)',
            'ceb' => 'Cebuano',
            'zh_CN' => 'Chinese (China)',
            'zh_HK' => 'Chinese (Hong Kong)',
            'zh_TW' => 'Chinese (Taiwan)',
            'co' => 'Corsican',
            'hr' => 'Croatian',
            'cs_CZ' => 'Czech',
            'da_DK' => 'Danish',
            'dv' => 'Dhivehi',
            'nl_NL' => 'Dutch',
            'nl_BE' => 'Dutch (Belgium)',
            'dzo' => 'Dzongkha',
            'art_xemoji' => 'Emoji',
            'en_US' => 'English',
            'en_AU' => 'English (Australia)',
            'en_CA' => 'English (Canada)',
            'en_NZ' => 'English (New Zealand)',
            'en_SA' => 'English (South Africa)',
            'en_GB' => 'English (UK)',
            'eo' => 'Esperanto',
            'et' => 'Estonian',
            'fo' => 'Faroese',
            'fi' => 'Finnish',
            'fr_BE' => 'French (Belgium)',
            'fr_CA' => 'French (Canada)',
            'fr_FR' => 'French (France)',
            'fy' => 'Frisian',
            'fur' => 'Friulian',
            'fuc' => 'Fulah',
            'gl_ES' => 'Galician',
            'ka_GE' => 'Georgian',
            'de_DE' => 'German',
            'de_CH' => 'German (Switzerland)',
            'el' => 'Greek',
            'kal' => 'Greenlandic',
            'gn' => 'Guaraní',
            'gu' => 'Gujarati',
            'haw_US' => 'Hawaiian',
            'haz' => 'Hazaragi',
            'he_IL' => 'Hebrew',
            'hi_IN' => 'Hindi',
            'hu_HU' => 'Hungarian',
            'is_IS' => 'Icelandic',
            'ido' => 'Ido',
            'id_ID' => 'Indonesian',
            'ga' => 'Irish',
            'it_IT' => 'Italian',
            'ja' => 'Japanese',
            'jv_ID' => 'Javanese',
            'kab' => 'Kabyle',
            'kn' => 'Kannada',
            'kk' => 'Kazakh',
            'km' => 'Khmer',
            'kin' => 'Kinyarwanda',
            'ky_KY' => 'Kirghiz',
            'ko_KR' => 'Korean',
            'ckb' => 'Kurdish (Sorani)',
            'lo' => 'Lao',
            'lv' => 'Latvian',
            'li' => 'Limburgish',
            'lin' => 'Lingala',
            'lt_LT' => 'Lithuanian',
            'lb_LU' => 'Luxembourgish',
            'mk_MK' => 'Macedonian',
            'mg_MG' => 'Malagasy',
            'ms_MY' => 'Malay',
            'ml_IN' => 'Malayalam',
            'mri' => 'Maori',
            'mr' => 'Marathi',
            'xmf' => 'Mingrelian',
            'mn' => 'Mongolian',
            'me_ME' => 'Montenegrin',
            'ary' => 'Moroccan Arabic',
            'my_MM' => 'Myanmar (Burmese)',
            'ne_NP' => 'Nepali',
            'nb_NO' => 'Norwegian (Bokmål)',
            'nn_NO' => 'Norwegian (Nynorsk)',
            'oci' => 'Occitan',
            'ory' => 'Oriya',
            'os' => 'Ossetic',
            'ps' => 'Pashto',
            'fa_IR' => 'Persian',
            'fa_AF' => 'Persian (Afghanistan)',
            'pl_PL' => 'Polish',
            'pt_BR' => 'Portuguese (Brazil)',
            'pt_PT' => 'Portuguese (Portugal)',
            'pa_IN' => 'Punjabi',
            'rhg' => 'Rohingya',
            'ro_RO' => 'Romanian',
            'roh' => 'Romansh Vallader',
            'ru_RU' => 'Russian',
            'rue' => 'Rusyn',
            'sah' => 'Sakha',
            'sa_IN' => 'Sanskrit',
            'srd' => 'Sardinian',
            'gd' => 'Scottish Gaelic',
            'sr_RS' => 'Serbian',
            'szl' => 'Silesian',
            'snd' => 'Sindhi',
            'si_LK' => 'Sinhala',
            'sk_SK' => 'Slovak',
            'sl_SI' => 'Slovenian',
            'so_SO' => 'Somali',
            'azb' => 'South Azerbaijani',
            'es_AR' => 'Spanish (Argentina)',
            'es_CL' => 'Spanish (Chile)',
            'es_CO' => 'Spanish (Colombia)',
            'es_GT' => 'Spanish (Guatemala)',
            'es_MX' => 'Spanish (Mexico)',
            'es_PE' => 'Spanish (Peru)',
            'es_PR' => 'Spanish (Puerto Rico)',
            'es_ES' => 'Spanish (Spain)',
            'es_VE' => 'Spanish (Venezuela)',
            'su_ID' => 'Sundanese',
            'sw' => 'Swahili',
            'sv_SE' => 'Swedish',
            'gsw' => 'Swiss German',
            'tl' => 'Tagalog',
            'tah' => 'Tahitian',
            'tg' => 'Tajik',
            'tzm' => 'Tamazight (Central Atlas)',
            'ta_IN' => 'Tamil',
            'ta_LK' => 'Tamil (Sri Lanka)',
            'tt_RU' => 'Tatar',
            'te' => 'Telugu',
            'th' => 'Thai',
            'bo' => 'Tibetan',
            'tir' => 'Tigrinya',
            'tr_TR' => 'Turkish',
            'tuk' => 'Turkmen',
            'twd' => 'Tweants',
            'ug_CN' => 'Uighur',
            'uk' => 'Ukrainian',
            'ur' => 'Urdu',
            'uz_UZ' => 'Uzbek',
            'vi' => 'Vietnamese',
            'wa' => 'Walloon',
            'cy' => 'Welsh',
            'yor' => 'Yoruba',
        );
    }

    public static function install_and_set_language( string $locale = '' ): void {
        if ( $locale === 'en_US' ) {
            update_option( 'WPLANG', '' );
            self::update_date_time_format_options( $locale );
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        $available_languages = get_available_languages();
        if ( ! in_array( $locale, $available_languages, true ) ) {
            $language = wp_download_language_pack( $locale );

            if ( ! $language ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "Hostinger AI theme: Core language pack not installed: " . $locale );
                }
            }
        }

        $language_set = update_option( 'WPLANG', $locale );

        wp_cache_flush();

        delete_transient( 'update_core' );
        delete_transient( 'update_plugins' );
        delete_transient( 'update_themes' );

        delete_site_transient( 'update_core' );
        delete_site_transient( 'update_plugins' );
        delete_site_transient( 'update_themes' );

        if ( $language_set ) {
            unload_textdomain( 'default' );
            load_default_textdomain( $locale );
        } else {
            if ( ! is_textdomain_loaded( 'default' ) ) {
                load_default_textdomain( $locale );
            }
        }

        self::update_date_time_format_options( $locale );

        wp_clean_update_cache();
        wp_version_check();
        wp_update_plugins();
        wp_update_themes();

        $updates = wp_get_translation_updates();
        if ( ! empty( $updates ) ) {
            if ( ! class_exists( 'Language_Pack_Upgrader' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
                require_once ABSPATH . 'wp-admin/includes/class-language-pack-upgrader.php';
            }

            if ( ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
            }

            if ( ! class_exists( 'Automatic_Upgrader_Skin' ) || ! class_exists( 'Language_Pack_Upgrader' ) || ! class_exists( 'WP_Upgrader' ) ) {
                return;
            }

            $upgrader = new Language_Pack_Upgrader( new Automatic_Upgrader_Skin() );

            foreach ( $updates as $update ) {
                $result = $upgrader->upgrade( $update );

                if ( is_wp_error( $result ) ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log('Hostinger AI Theme: Translation install error: ' . $result->get_error_message());
                    }
                }
            }
        }
    }

    private static function update_date_time_format_options( string $locale ): void {
        $defaults     = self::get_locale_date_time_defaults();
        $default_date = 'F j, Y';
        $default_time = 'g:i a';

        if ( $locale === 'en_US' ) {
            $date_format = $default_date;
            $time_format = $default_time;
        } else {
            $translated_date = __( 'F j, Y' );
            $translated_time = __( 'g:i a' );

            $date_format = ( $translated_date !== $default_date )
                ? $translated_date
                : ( $defaults[ $locale ]['date_format'] ?? $default_date );

            $time_format = ( $translated_time !== $default_time )
                ? $translated_time
                : ( $defaults[ $locale ]['time_format'] ?? $default_time );
        }

        update_option( 'date_format', $date_format );
        update_option( 'time_format', $time_format );
    }

    private static function get_locale_date_time_defaults(): array {
        return array(
            'id_ID' => array( 'date_format' => 'j F Y',    'time_format' => 'H:i' ),
            'lt_LT' => array( 'date_format' => 'Y-m-d',    'time_format' => 'G:i' ),
            'tr_TR' => array( 'date_format' => 'j F Y',    'time_format' => 'H:i' ),
            'de_DE' => array( 'date_format' => 'j. F Y',   'time_format' => 'H:i' ),
            'nl_NL' => array( 'date_format' => 'j F Y',    'time_format' => 'H:i' ),
            'uk'    => array( 'date_format' => 'j F Y',    'time_format' => 'H:i' ),
            'en_US' => array( 'date_format' => 'F j, Y',   'time_format' => 'g:i a' ),
            'pl_PL' => array( 'date_format' => 'j F Y',    'time_format' => 'H:i' ),
            'th'    => array( 'date_format' => 'j F Y',    'time_format' => 'H:i' ),
            'es_AR' => array( 'date_format' => 'j de F de Y', 'time_format' => 'H:i' ),
            'pt_PT' => array( 'date_format' => 'j de F de Y', 'time_format' => 'H:i' ),
            'zh_CN' => array( 'date_format' => 'Y年n月j日',  'time_format' => 'ag:i' ),
            'es_CO' => array( 'date_format' => 'j de F de Y', 'time_format' => 'H:i' ),
            'pt_BR' => array( 'date_format' => 'j \\d\\e F \\d\\e Y', 'time_format' => 'H:i' ),
            'hi_IN' => array( 'date_format' => 'j F Y',    'time_format' => 'g:i a' ),
            'es_MX' => array( 'date_format' => 'j de F de Y', 'time_format' => 'H:i' ),
            'ro_RO' => array( 'date_format' => 'j F Y',    'time_format' => 'H:i' ),
            'es_ES' => array( 'date_format' => 'j de F de Y', 'time_format' => 'H:i' ),
            'ar'    => array( 'date_format' => 'j F Y',    'time_format' => 'g:i a' ),
            'fr_FR' => array( 'date_format' => 'j F Y',    'time_format' => 'H:i' ),
            'he_IL' => array( 'date_format' => 'j בF Y',   'time_format' => 'H:i' ),
            'it_IT' => array( 'date_format' => 'j F Y',    'time_format' => 'H:i' ),
            'vi'    => array( 'date_format' => 'j F, Y',   'time_format' => 'H:i' ),
            'ja'    => array( 'date_format' => 'Y年n月j日',  'time_format' => 'H:i' ),
            'ko_KR' => array( 'date_format' => 'Y년 n월 j일', 'time_format' => 'a g:i' ),
        );
    }

    // Setup deactivates LiteSpeed so generation is not served from a stale cache.
    public static function activate_litespeed_cache(): void {
        if ( ! function_exists( 'activate_plugin' ) || ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! is_plugin_active( self::LITESPEED_PLUGIN ) ) {
            activate_plugin( self::LITESPEED_PLUGIN, '', false, true );
        }

        if ( has_action( 'litespeed_purge_all' ) ) {
            do_action( 'litespeed_purge_all' );
        }

        wp_cache_flush();
    }
}
