<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use stdClass;
use WP_Error;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class LiteSpeedCacheTools {
    protected string $type = 'tool';

    public function register(): void {
        if ( ! $this->is_litespeed_cache_active() ) {
            return;
        }

        $this->register_get_settings_operation();
        $this->register_flush_cache_operation();
        $this->register_list_presets_operation();
        $this->register_apply_preset_operation();
    }

    private function register_get_settings_operation(): void {
        $ability_args = array(
            'label'               => __( 'Get LiteSpeed Cache Settings', 'hostinger-ai-assistant' ),
            'description'         => __( 'Get current LiteSpeed Cache settings including active preset, cache status, and key configuration options.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => new stdClass(),
            ),
            'execute_callback'    => array( $this, 'execute_get_settings_operation' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'         => 'Get LiteSpeed Cache Settings',
                    'readOnlyHint'  => true,
                    'openWorldHint' => false,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/litespeed-cache-settings-get', $ability_args );
    }

    private function register_flush_cache_operation(): void {
        $ability_args = array(
            'label'               => __( 'Flush LiteSpeed Cache', 'hostinger-ai-assistant' ),
            'description'         => __( 'Purge all LiteSpeed caches including LSCache, CSS/JS, localized resources, object cache, and opcache.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => new stdClass(),
            ),
            'execute_callback'    => array( $this, 'execute_flush_cache_operation' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'           => 'Flush LiteSpeed Cache',
                    'readOnlyHint'    => false,
                    'destructiveHint' => true,
                    'idempotentHint'  => true,
                    'openWorldHint'   => false,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/litespeed-cache-flush', $ability_args );
    }

    private function register_list_presets_operation(): void {
        $ability_args = array(
            'label'               => __( 'List LiteSpeed Cache Presets', 'hostinger-ai-assistant' ),
            'description'         => __( 'Get a list of all available LiteSpeed Cache optimization presets with their descriptions, features, risk levels, and requirements.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => new stdClass(),
            ),
            'execute_callback'    => array( $this, 'execute_list_presets_operation' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'         => 'List LiteSpeed Cache Presets',
                    'readOnlyHint'  => true,
                    'openWorldHint' => false,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/litespeed-cache-presets-list', $ability_args );
    }

    private function register_apply_preset_operation(): void {
        $ability_args = array(
            'label'               => __( 'Apply LiteSpeed Cache Preset', 'hostinger-ai-assistant' ),
            'description'         => __( 'Apply a predefined optimization preset to LiteSpeed Cache settings. Available presets: basic (minimal optimization), advanced (balanced), aggressive (more optimization), essentials (core features), extreme (maximum optimization). A backup of current settings will be created automatically.', 'hostinger-ai-assistant' ),
            'category'            => 'hostinger-ai-assistant',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'preset' => array(
                        'type'        => 'string',
                        'description' => __( 'The preset to apply', 'hostinger-ai-assistant' ),
                        'enum'        => array( 'basic', 'advanced', 'aggressive', 'essentials', 'extreme' ),
                    ),
                ),
                'required'   => array( 'preset' ),
            ),
            'execute_callback'    => array( $this, 'execute_apply_preset_operation' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array(
                    'public' => true,
                    'type'   => $this->type,
                ),
                'annotations'  => array(
                    'title'           => 'Apply LiteSpeed Cache Preset',
                    'readOnlyHint'    => false,
                    'destructiveHint' => true,
                    'idempotentHint'  => false,
                    'openWorldHint'   => false,
                ),
            ),
        );

        wp_register_ability( 'hostinger-ai/litespeed-cache-preset', $ability_args );
    }

    public function execute_get_settings_operation(): WP_Error|array {
        if ( ! $this->is_litespeed_cache_active() ) {
            return new WP_Error(
                'litespeed_cache_not_active',
                __( 'LiteSpeed Cache plugin is not active', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        try {
            $summary = \LiteSpeed\Import::get_summary();
            $conf    = \LiteSpeed\Conf::cls();

            $settings = array(
                'plugin_version'             => defined( 'LSCWP_V' ) ? LSCWP_V : null,
                'preset_applied_at'          => ! empty( $summary['preset_timestamp'] ) ? gmdate( 'Y-m-d H:i:s', $summary['preset_timestamp'] ) : null,
                'cache_enabled'              => (bool) $conf->conf( \LiteSpeed\Base::O_CACHE ),
                'cache_mobile_enabled'       => (bool) $conf->conf( \LiteSpeed\Base::O_CACHE_MOBILE ),
                'cache_browser_enabled'      => (bool) $conf->conf( \LiteSpeed\Base::O_CACHE_BROWSER ),
                'cache_ttl_public'           => (int) $conf->conf( \LiteSpeed\Base::O_CACHE_TTL_PUB ),
                'cache_ttl_private'          => (int) $conf->conf( \LiteSpeed\Base::O_CACHE_TTL_PRIV ),
                'cache_ttl_frontpage'        => (int) $conf->conf( \LiteSpeed\Base::O_CACHE_TTL_FRONTPAGE ),
                'cache_ttl_browser'          => (int) $conf->conf( \LiteSpeed\Base::O_CACHE_TTL_BROWSER ),
                'object_cache_enabled'       => defined( 'LSCWP_OBJECT_CACHE' ),
                'css_minify_enabled'         => (bool) $conf->conf( \LiteSpeed\Base::O_OPTM_CSS_MIN ),
                'js_minify_enabled'          => (bool) $conf->conf( \LiteSpeed\Base::O_OPTM_JS_MIN ),
                'html_minify_enabled'        => (bool) $conf->conf( \LiteSpeed\Base::O_OPTM_HTML_MIN ),
                'css_combine_enabled'        => (bool) $conf->conf( \LiteSpeed\Base::O_OPTM_CSS_COMB ),
                'js_combine_enabled'         => (bool) $conf->conf( \LiteSpeed\Base::O_OPTM_JS_COMB ),
                'image_optimization_enabled' => (bool) $conf->conf( \LiteSpeed\Base::O_IMG_OPTM_AUTO ),
                'lazy_load_images_enabled'   => (bool) $conf->conf( \LiteSpeed\Base::O_MEDIA_LAZY ),
            );

            return $settings;
        } catch ( Exception $e ) {
            return new WP_Error(
                'litespeed_get_settings_failed',
                /* translators: %s: Setting name */
                sprintf( __( 'Failed to get settings: %s', 'hostinger-ai-assistant' ), $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    public function execute_flush_cache_operation(): WP_Error|array {
        if ( ! $this->is_litespeed_cache_active() ) {
            return new WP_Error(
                'litespeed_cache_not_active',
                __( 'LiteSpeed Cache plugin is not active', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        try {
            if ( ! defined( 'LITESPEED_PURGE_SILENT' ) ) {
                define( 'LITESPEED_PURGE_SILENT', true );
            }

            \LiteSpeed\Purge::purge_all( 'MCP API Request' );

            return array(
                'success' => true,
                'message' => __( 'All LiteSpeed caches have been purged successfully.', 'hostinger-ai-assistant' ),
            );
        } catch ( Exception $e ) {
            return new WP_Error(
                'litespeed_cache_flush_failed',
                /* translators: %s: Error message */
                sprintf( __( 'Failed to flush cache: %s', 'hostinger-ai-assistant' ), $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    public function execute_list_presets_operation(): WP_Error|array {
        if ( ! $this->is_litespeed_cache_active() ) {
            return new WP_Error(
                'litespeed_cache_not_active',
                __( 'LiteSpeed Cache plugin is not active', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        $presets = array(
            'essentials' => array(
                'name'                => 'Essentials',
                'risk_level'          => 'no-risk',
                'description'         => __( 'This no-risk preset is appropriate for all websites. Good for new users, simple websites, or cache-oriented development.', 'hostinger-ai-assistant' ),
                'quic_cloud_required' => false,
                'features'            => array(
                    __( 'Default Cache', 'hostinger-ai-assistant' ),
                    __( 'Higher TTL', 'hostinger-ai-assistant' ),
                    __( 'Browser Cache', 'hostinger-ai-assistant' ),
                ),
                'good_for'            => __( 'All websites, new users, simple websites, or cache-oriented development', 'hostinger-ai-assistant' ),
            ),
            'basic'      => array(
                'name'                => 'Basic',
                'risk_level'          => 'low-risk',
                'description'         => __( 'This low-risk preset introduces basic optimizations for speed and user experience. Appropriate for enthusiastic beginners.', 'hostinger-ai-assistant' ),
                'quic_cloud_required' => true,
                'features'            => array(
                    __( 'Everything in Essentials', 'hostinger-ai-assistant' ),
                    __( 'Image Optimization', 'hostinger-ai-assistant' ),
                    __( 'Mobile Cache', 'hostinger-ai-assistant' ),
                ),
                'good_for'            => __( 'Enthusiastic beginners', 'hostinger-ai-assistant' ),
                'note'                => __( 'Includes optimizations known to improve site score in page speed measurement tools.', 'hostinger-ai-assistant' ),
            ),
            'advanced'   => array(
                'name'                => 'Advanced',
                'risk_level'          => 'low-risk',
                'recommended'         => true,
                'description'         => __( 'This preset is good for most websites, and is unlikely to cause conflicts. Any CSS or JS conflicts may be resolved with Page Optimization > Tuning tools.', 'hostinger-ai-assistant' ),
                'quic_cloud_required' => true,
                'features'            => array(
                    __( 'Everything in Basic', 'hostinger-ai-assistant' ),
                    __( 'Guest Mode and Guest Optimization', 'hostinger-ai-assistant' ),
                    __( 'CSS, JS and HTML Minification', 'hostinger-ai-assistant' ),
                    __( 'Font Display Optimization', 'hostinger-ai-assistant' ),
                    __( 'JS Defer for both external and inline JS', 'hostinger-ai-assistant' ),
                    __( 'DNS Prefetch for static files', 'hostinger-ai-assistant' ),
                    __( 'Gravatar Cache', 'hostinger-ai-assistant' ),
                    __( 'Remove Query Strings from Static Files', 'hostinger-ai-assistant' ),
                    __( 'Remove WordPress Emoji', 'hostinger-ai-assistant' ),
                    __( 'Remove Noscript Tags', 'hostinger-ai-assistant' ),
                ),
                'good_for'            => __( 'Most websites', 'hostinger-ai-assistant' ),
                'note'                => __( 'Includes many optimizations known to improve page speed scores.', 'hostinger-ai-assistant' ),
            ),
            'aggressive' => array(
                'name'                => 'Aggressive',
                'risk_level'          => 'medium-risk',
                'description'         => __( 'This preset might work out of the box for some websites, but be sure to test! Some CSS or JS exclusions may be necessary in Page Optimization > Tuning.', 'hostinger-ai-assistant' ),
                'quic_cloud_required' => true,
                'features'            => array(
                    __( 'Everything in Advanced', 'hostinger-ai-assistant' ),
                    __( 'CSS & JS Combine', 'hostinger-ai-assistant' ),
                    __( 'Asynchronous CSS Loading with Critical CSS', 'hostinger-ai-assistant' ),
                    __( 'Removed Unused CSS for Users', 'hostinger-ai-assistant' ),
                    __( 'Lazy Load for Iframes', 'hostinger-ai-assistant' ),
                ),
                'good_for'            => __( 'Websites willing to test and tune settings', 'hostinger-ai-assistant' ),
                'note'                => __( 'Includes many optimizations known to improve page speed scores. Testing recommended.', 'hostinger-ai-assistant' ),
            ),
            'extreme'    => array(
                'name'                => 'Extreme',
                'risk_level'          => 'high-risk',
                'description'         => __( 'This preset almost certainly will require testing and exclusions for some CSS, JS and Lazy Loaded images. Pay special attention to logos, or HTML-based slider images.', 'hostinger-ai-assistant' ),
                'quic_cloud_required' => true,
                'features'            => array(
                    __( 'Everything in Aggressive', 'hostinger-ai-assistant' ),
                    __( 'Lazy Load for Images', 'hostinger-ai-assistant' ),
                    __( 'Viewport Image Generation', 'hostinger-ai-assistant' ),
                    __( 'JS Delayed', 'hostinger-ai-assistant' ),
                    __( 'Inline JS added to Combine', 'hostinger-ai-assistant' ),
                    __( 'Inline CSS added to Combine', 'hostinger-ai-assistant' ),
                ),
                'good_for'            => __( 'Advanced users willing to test and exclude items', 'hostinger-ai-assistant' ),
                'note'                => __( 'Enables the maximum level of optimizations for improved page speed scores. Extensive testing required.', 'hostinger-ai-assistant' ),
            ),
        );

        return array(
            'presets' => $presets,
        );
    }

    public function execute_apply_preset_operation( array $input ): WP_Error|array {
        if ( ! $this->is_litespeed_cache_active() ) {
            return new WP_Error(
                'litespeed_cache_not_active',
                __( 'LiteSpeed Cache plugin is not active', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        if ( empty( $input['preset'] ) ) {
            return new WP_Error(
                'missing_preset',
                __( 'Preset parameter is required', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        $preset          = sanitize_text_field( $input['preset'] );
        $allowed_presets = array( 'basic', 'advanced', 'aggressive', 'essentials', 'extreme' );

        if ( ! in_array( $preset, $allowed_presets, true ) ) {
            return new WP_Error(
                'invalid_preset',
                sprintf(
                    /* translators: %s: Allowed preset name */
                    __( 'Invalid preset. Allowed presets: %s', 'hostinger-ai-assistant' ),
                    implode( ', ', $allowed_presets )
                ),
                array( 'status' => 400 )
            );
        }

        try {
            $preset_handler = new \LiteSpeed\Preset();

            $preset_handler->apply( $preset );

            return array(
                'success' => true,
                'preset'  => $preset,
                'message' => sprintf(
                    /* translators: %s: Preset name */
                    __( 'LiteSpeed Cache preset "%s" has been applied successfully. A backup of your previous settings was created automatically.', 'hostinger-ai-assistant' ),
                    $preset
                ),
            );
        } catch ( Exception $e ) {
            return new WP_Error(
                'litespeed_preset_apply_failed',
                /* translators: %s: Preset name */
                sprintf( __( 'Failed to apply preset: %s', 'hostinger-ai-assistant' ), $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    protected function is_litespeed_cache_active(): bool {
        return defined( 'LSCWP_V' )
            && class_exists( 'LiteSpeed\Purge' )
            && class_exists( 'LiteSpeed\Preset' );
    }
}
