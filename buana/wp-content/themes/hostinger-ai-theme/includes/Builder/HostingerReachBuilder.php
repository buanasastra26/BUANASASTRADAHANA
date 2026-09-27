<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class HostingerReachBuilder extends AbstractPluginBuilder {
    use HostingerPluginUpdateUri;

    private const PLUGIN_FILE                = 'hostinger-reach/hostinger-reach.php';
    private const PLUGIN_NAME                = 'Hostinger Reach';
    public const PLUGIN_SLUG                 = 'hostinger-reach';
    public const FORM_ID                     = 'ai-theme-footer-form';
    private const FORMS_TABLE                = 'hostinger_reach_forms';
    private const INTEGRATIONS_OPTION        = 'hostinger_reach_integrations';
    private const ELEMENTOR_INTEGRATION      = 'elementor';
    private const IS_ACTIVE_KEY              = 'is_active';
    private const API_KEY_OPTION             = 'hostinger_reach_api_key';
    private const UNCONNECTED_WIDGET_VERSION = '1.8.3';

    protected function get_plugin_file(): string {
        return self::PLUGIN_FILE;
    }

    protected function get_plugin_name(): string {
        return self::PLUGIN_NAME;
    }

    protected function get_download_url(): string {
        return $this->build_hostinger_download_url( self::PLUGIN_SLUG );
    }

    protected function get_error_code(): string {
        return 'hostinger_reach';
    }

    protected function get_plugin_version(): string {
        return defined( 'HOSTINGER_REACH_PLUGIN_VERSION' ) ? (string) HOSTINGER_REACH_PLUGIN_VERSION : '';
    }

    protected function after_activation(): void {
        $this->generate_form();
        $this->activate_elementor_integration();
    }

    // Reach before 1.8.3 registers the Elementor widget only with this on; later releases read it for form sync.
    public function activate_elementor_integration(): void {
        if ( ! $this->is_plugin_active() || ! Helper::is_elementor_active() ) {
            return;
        }

        $integrations = get_option( self::INTEGRATIONS_OPTION, array() );
        $integrations = is_array( $integrations ) ? $integrations : array();

        if ( isset( $integrations[ self::ELEMENTOR_INTEGRATION ][ self::IS_ACTIVE_KEY ] ) ) {
            return;
        }

        $integrations[ self::ELEMENTOR_INTEGRATION ][ self::IS_ACTIVE_KEY ] = true;

        update_option( self::INTEGRATIONS_OPTION, $integrations );
    }

    // Mirrors Reach's own gates. An installed Reach is never updated, so older versions still appear here.
    public function can_render_elementor_form(): bool {
        if ( ! $this->is_plugin_active() || ! Helper::is_elementor_active() ) {
            return false;
        }

        if ( version_compare( $this->get_plugin_version(), self::UNCONNECTED_WIDGET_VERSION, '>=' ) ) {
            return true;
        }

        $integrations = get_option( self::INTEGRATIONS_OPTION, array() );
        $is_active    = is_array( $integrations )
            ? ( $integrations[ self::ELEMENTOR_INTEGRATION ][ self::IS_ACTIVE_KEY ] ?? false )
            : false;

        return (bool) $is_active && (string) get_option( self::API_KEY_OPTION, '' ) !== '';
    }

    public function generate_form(): void {
        global $wpdb;

        if ( ! $this->is_plugin_active() ) {
            return;
        }

        $table_name = $wpdb->prefix . self::FORMS_TABLE;

        $form_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE form_id = %s",
                self::FORM_ID
            )
        );

        if ( ! $form_exists ) {
            $footer_post = get_posts(
                array(
                    'name'        => 'footer',
                    'post_type'   => 'wp_template_part',
                    'numberposts' => 1,
                    'fields'      => 'ids',
                )
            );

            $post_id = ! empty( $footer_post ) ? array_shift( $footer_post ) : 0;

            $wpdb->insert(
                $table_name,
                array(
                    'form_id'    => self::FORM_ID,
                    'post_id'    => $post_id,
                    'type'       => 'hostinger-reach',
                    'is_active'  => 1,
                    'form_title' => 'Footer',
                )
            );
        }
    }
}
