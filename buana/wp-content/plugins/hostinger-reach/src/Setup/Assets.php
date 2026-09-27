<?php

namespace Hostinger\Reach\Setup;

use Hostinger\Reach\Api\Handlers\ReachApiHandler;
use Hostinger\Reach\Api\ResourceIdManager;
use Hostinger\Reach\Functions;
use Hostinger\Reach\Integrations\Elementor\ElementorIntegration;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class Assets {
    private Functions $functions;
    private ReachApiHandler $reach_api_handler;

    public function __construct( Functions $functions, ReachApiHandler $reach_api_handler ) {
        $this->functions         = $functions;
        $this->reach_api_handler = $reach_api_handler;
    }

    public function init(): void {
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
    }

    public function admin_enqueue_scripts(): void {
        global $wpdb;

        if ( ! $this->functions->need_to_load_assets() ) {
            return;
        }

        if ( file_exists( $this->functions->get_frontend_dir() . 'main.js' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'hostinger-reach',
            Functions::get_frontend_url() . 'main.js',
            array(),
            filemtime( $this->functions->get_frontend_dir() . 'main.js' ),
            true
        );

        $css_file = $this->functions->get_frontend_dir() . 'main.css';
        if ( file_exists( $css_file ) ) {
            wp_enqueue_style(
                'hostinger-reach-styles',
                Functions::get_frontend_url() . 'main.css',
                array(),
                filemtime( $css_file )
            );
        }

        $siteurl = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                'siteurl'
            )
        );

        $siteurl    = preg_replace( '#^https?://(?:www\.)?#i', '', $siteurl );
        $raw_domain = implode( ' ', str_split( $siteurl ) );

        wp_localize_script(
            'hostinger-reach',
            'hostinger_reach_reach_data',
            array(
                'site_url'               => get_site_url(),
                'rest_base_url'          => esc_url_raw( rest_url() ),
                'ajax_url'               => admin_url( 'admin-ajax.php' ),
                'nonce'                  => wp_create_nonce( 'wp_rest' ),
                'add_block_nonce'        => wp_create_nonce( 'hostinger_reach_add_block' ),
                'admin_url'              => admin_url(),
                'plugin_url'             => HOSTINGER_REACH_PLUGIN_URL,
                'translations'           => $this->get_translations(),
                'is_connected'           => $this->reach_api_handler->is_connected(),
                'is_hostinger_user'      => $this->functions->is_hostinger_user(),
                'is_staging'             => $this->functions->is_staging(),
                'total_form_pages'       => (int) wp_count_posts( 'page' )->publish + (int) wp_count_posts( 'page' )->draft,
                'has_valid_resource_id'  => ! empty( $this->reach_api_handler->get_resource_id() ) && $this->reach_api_handler->get_resource_id() !== ResourceIdManager::NON_EXISTENT_RESOURCE_ID,
                'resource_id'            => $this->reach_api_handler->get_resource_id(),
                'domain'                 => $this->functions->get_host_info(),
                'raw_domain'             => $raw_domain,
                'is_elementor_active'    => ElementorIntegration::is_active(),
                'elementor_new_page_url' => ElementorIntegration::get_new_page_url(),
                'embed_script_url'       => HOSTINGER_REACH_EMBED_SCRIPT_URL,
            )
        );
    }

    private function get_translations(): array {
        return array(
            'hostinger_reach_back'                                        => __( 'Back', 'hostinger-reach' ),
            'hostinger_reach_error_message'                               => __( 'Something went wrong', 'hostinger-reach' ),
            'hostinger_reach_welcome_view_title'                          => __( 'Welcome to Reach', 'hostinger-reach' ),
            'hostinger_reach_welcome_view_description'                    => __( 'Email marketing that doesn\'t need an expert: Let AI write, design, and send your campaigns - your brand, your colors, applied automatically. No coding, no complexity.', 'hostinger-reach' ),
            'hostinger_reach_welcome_view_connection_warning'             => __( 'Reach is already connected to another site.', 'hostinger-reach' ),
            'hostinger_reach_welcome_view_connection_instruction'         => __( 'Disconnect it to link this site instead.', 'hostinger-reach' ),
            'hostinger_reach_welcome_view_manage_button'                  => __( 'Manage', 'hostinger-reach' ),
            'hostinger_reach_welcome_view_start_button'                   => __( 'Connect site', 'hostinger-reach' ),
            'hostinger_reach_api_key_modal_link'                          => __( 'Connect with API token', 'hostinger-reach' ),
            'hostinger_reach_reach_api_key_modal_title'                   => __( 'Connect your website to Hostinger Reach', 'hostinger-reach' ),
            'hostinger_reach_reach_api_key_modal_steps_title'             => __( 'Generate an API token', 'hostinger-reach' ),
            'hostinger_reach_reach_api_key_modal_steps_one'               => __( 'Go to', 'hostinger-reach' ),
            'hostinger_reach_reach_api_key_modal_steps_one_link'          => __( 'Hostinger Reach Integrations → API Tokens', 'hostinger-reach' ),
            'hostinger_reach_reach_api_key_modal_steps_two'               => __( 'Click <strong>Generate API Token</strong>', 'hostinger-reach' ),
            'hostinger_reach_reach_api_key_modal_steps_three'             => __( 'Name your token, then click <strong>Generate token</strong>', 'hostinger-reach' ),
            'hostinger_reach_reach_api_key_modal_steps_four'              => __( '<strong>Copy</strong> your new API token and <strong>paste it below</strong>', 'hostinger-reach' ),
            'hostinger_reach_reach_api_key_modal_field_label'             => __( 'Your API token', 'hostinger-reach' ),
            'hostinger_reach_reach_api_key_modal_field_placholder'        => __( 'Paste your API token here', 'hostinger-reach' ),
            'hostinger_reach_error_connection_failed'                     => __( 'Connection failed. Check if your API Token is correct or contact Hostinger Reach support.', 'hostinger-reach' ),
            'hostinger_reach_error_no_key'                                => __( 'API Token field is required', 'hostinger-reach' ),
            'hostinger_reach_reach_api_key_modal_button'                  => __( 'Connect', 'hostinger-reach' ),
            'hostinger_reach_reach_api_key_modal_cancel'                  => __( 'Cancel', 'hostinger-reach' ),
            'hostinger_reach_welcome_view_not_active_button'              => __( 'Review domain', 'hostinger-reach' ),
            'hostinger_reach_header_go_to_reach_button'                   => __( 'Go to Reach', 'hostinger-reach' ),
            'hostinger_reach_header_logo_alt'                             => __( 'Hostinger Reach', 'hostinger-reach' ),
            'hostinger_reach_hero_background_alt'                         => __( 'Email marketing background with gradient design', 'hostinger-reach' ),
            'hostinger_reach_hero_overlay_alt'                            => __( 'Email marketing illustration featuring envelopes and communication icons', 'hostinger-reach' ),
            'hostinger_reach_overview_title'                              => __( 'This month', 'hostinger-reach' ),
            'hostinger_reach_overview_your_plan_button'                   => __( 'Plan & Limits', 'hostinger-reach' ),
            'hostinger_reach_overview_contacts_button'                    => __( 'Contacts', 'hostinger-reach' ),
            'hostinger_reach_overview_segments_button'                    => __( 'Segments', 'hostinger-reach' ),
            'hostinger_reach_overview_upgrade_button'                     => __( 'Upgrade', 'hostinger-reach' ),
            'hostinger_reach_overview_emails_title'                       => __( 'Emails', 'hostinger-reach' ),
            'hostinger_reach_overview_emails_sent_label'                  => __( 'Sent', 'hostinger-reach' ),
            'hostinger_reach_overview_emails_remaining_label'             => __( 'Remaining', 'hostinger-reach' ),
            'hostinger_reach_overview_campaigns_title'                    => __( 'Campaigns', 'hostinger-reach' ),
            'hostinger_reach_overview_campaigns_sent_label'               => __( 'Sent', 'hostinger-reach' ),
            'hostinger_reach_overview_campaigns_ctor_label'               => __( 'Average CTOR', 'hostinger-reach' ),
            'hostinger_reach_overview_subscribers_title'                  => __( 'Subscribers', 'hostinger-reach' ),
            'hostinger_reach_overview_subscribers_new_label'              => __( 'New subscribers', 'hostinger-reach' ),
            'hostinger_reach_overview_subscribers_unsubscribes_label'     => __( 'Unsubscribes', 'hostinger-reach' ),
            'hostinger_reach_overview_subscribers_total_label'            => __( 'Total subscribers', 'hostinger-reach' ),
            'hostinger_reach_overview_campaigns_text'                     => __( 'Create campaign', 'hostinger-reach' ),
            'hostinger_reach_overview_templates_text'                     => __( 'Create template', 'hostinger-reach' ),
            'hostinger_reach_overview_settings_text'                      => __( 'Settings', 'hostinger-reach' ),
            'hostinger_reach_integrations_title'                          => __( 'Integrations', 'hostinger-reach' ),
            'hostinger_reach_ecommerce_title'                             => __( 'E-Commerce', 'hostinger-reach' ),
            'hostinger_reach_ecommerce_banner_title'                      => __( 'Connect WooCommerce to boost sales with email automations', 'hostinger-reach' ),
            'hostinger_reach_ecommerce_banner_description'                => __( 'Collect contacts at checkout, send automated emails after purchases, and recover abandoned carts to drive more sales with Reach', 'hostinger-reach' ),
            'hostinger_reach_ecommerce_banner_button_text'                => __( 'Connect WooCommerce', 'hostinger-reach' ),
            'hostinger_reach_forms_title'                                 => __( 'Forms', 'hostinger-reach' ),
            'hostinger_reach_forms_banner_title'                          => __( 'Add or connect a form to start sending campaigns', 'hostinger-reach' ),
            'hostinger_reach_forms_banner_description'                    => __( 'Create a new form or connect a plugin to grow your contact list and start sending campaigns with Reach.', 'hostinger-reach' ),
            'hostinger_reach_forms_banner_button_text'                    => __( 'Add new form', 'hostinger-reach' ),
            'hostinger_reach_forms_banner_connect_text'                   => __( 'or connect a form plugin', 'hostinger-reach' ),
            'hostinger_reach_forms_banner_more_text'                      => __( 'More plugins', 'hostinger-reach' ),
            'hostinger_reach_add_form'                                    => __( 'Add form', 'hostinger-reach' ),
            'hostinger_reach_connect_plugin'                              => __( 'Connect plugin', 'hostinger-reach' ),
            'hostinger_reach_forms_add_more_button_text'                  => __( 'Add form or plugin', 'hostinger-reach' ),
            'hostinger_reach_forms_manage'                                => __( 'Manage plugin', 'hostinger-reach' ),
            'hostinger_reach_forms_new_page_text'                         => __( 'New page', 'hostinger-reach' ),
            'hostinger_reach_forms_no_pages_available'                    => __( 'No pages available. Create a new page to get started.', 'hostinger-reach' ),
            'hostinger_reach_forms_page_draft_suffix'                     => __( '(Draft)', 'hostinger-reach' ),
            'hostinger_reach_faq_title'                                   => __( 'FAQ', 'hostinger-reach' ),
            'hostinger_reach_faq_what_is_reach_question'                  => __( 'What is Hostinger Reach email marketing service?', 'hostinger-reach' ),
            'hostinger_reach_faq_what_is_reach_answer'                    => __( 'Hostinger Reach is an AI-powered email marketing tool for small businesses and creators. It supports your entire email marketing journey—from building contact lists to sending campaigns and tracking results.', 'hostinger-reach' ),
            'hostinger_reach_faq_how_different_question'                  => __( 'How is Hostinger Reach different from other email marketing apps?', 'hostinger-reach' ),
            'hostinger_reach_faq_how_different_answer'                    => __( 'Hostinger Reach is built for simplicity, speed, and results – no design or marketing experience needed. Unlike most email tools, at the core of Reach is its AI-powered template creator. Whether it is a product launch, special offer, or newsletter update, it instantly crafts a professional, mobile-friendly email. It not only writes the content for you; it also suggests the best layout for your message and saves your style settings so you\'re never starting from scratch.<br><br>Every template is customizable, so your emails reflect your brand\'s look, feel, and voice. And because the templates are built using proven best practices, they\'re optimized for readability, accessibility, and reader engagement.', 'hostinger-reach' ),
            'hostinger_reach_faq_how_much_cost_question'                  => __( 'How much does it cost to use Hostinger Reach?', 'hostinger-reach' ),
            'hostinger_reach_faq_how_much_cost_answer'                    => __( 'Reach offers a <b>free plan</b> for one year– perfect for getting started. Paid plans are based on how many unique contacts you aim to reach and how many emails you send monthly. As your audience grows, you can upgrade to a plan that fits your needs. Reach does not limit your contact list, so you don\'t need to worry about lost data and can consistently grow your audience.', 'hostinger-reach' ),
            'hostinger_reach_faq_what_is_plugin_difference_question'      => __( "What's the difference between Hostinger Reach and Hostinger Reach WordPress Plugin?", 'hostinger-reach' ),
            'hostinger_reach_faq_what_is_plugin_difference_answer'        => __( 'Hostinger Reach is a Hostinger service that helps you create and send branded email campaigns in minutes with smart AI tools. Hostinger Reach WordPress Plugin allows you to connect and sync contacts from your favorite WordPress plugins into Reach.', 'hostinger-reach' ),
            'hostinger_reach_faq_how_sync_works_question'                 => __( 'How does contact syncing work?', 'hostinger-reach' ),
            'hostinger_reach_faq_how_sync_works_answer'                   => __( 'Once you connect a form plugin you use on WordPress (such as Elementor or Contact Form 7), the plugin automatically forwards all the new form submissions and subscriber data to your Reach account, so your contacts are collected and updated without manual exports on each form submission', 'hostinger-reach' ),
            'hostinger_reach_faq_contacts_auto_added_question'            => __( 'Will contacts be added to my email list automatically?', 'hostinger-reach' ),
            'hostinger_reach_faq_contacts_auto_added_answer'              => __( 'Yes — new subscribers are added automatically based on your setup. Some integrations may also support importing previously collected contacts (historical data), depending on the plugin and connection flow. You’ll be able to review the available options during setup. You can manage subscriber settings like double opt-in, and adjust or organize segments directly in your Reach dashboard.', 'hostinger-reach' ),
            'hostinger_reach_faq_how_segments_created_question'           => __( 'How are segments and tags created?', 'hostinger-reach' ),
            'hostinger_reach_faq_how_segments_created_answer'             => __( 'Reach automatically tags synced contacts using the form name and also creates a segment for easier targeting. If the form doesn’t have a title, it uses the form location title. As a final fallback, the tag title will be the plugin name.If you create a form using Hostinger Reach option, you can also assign multiple existing or new tags to subscribers. Each form will automatically generate its own segment in Reach dashboard, making it easy to send campaigns to specific audiences — and you can fully manage or create additional segments anytime in Reach.', 'hostinger-reach' ),
            'hostinger_reach_faq_whatif_connect_breaks_question'          => __( 'What happens if the connection breaks?', 'hostinger-reach' ),
            'hostinger_reach_faq_whatif_connect_breaks_answer'            => __( 'If the integration is interrupted, syncing pauses automatically. Once the connection is restored, syncing resumes, so you can continue collecting contacts without losing data.', 'hostinger-reach' ),
            'hostinger_reach_ui_opens_in_new_tab'                         => __( 'opens in new tab', 'hostinger-reach' ),
            'hostinger_reach_ui_banner_background_image'                  => __( 'Banner background image for', 'hostinger-reach' ),
            'hostinger_reach_ui_background_image_for'                     => __( 'Background image for', 'hostinger-reach' ),
            'hostinger_reach_ui_usage_statistics'                         => __( 'usage statistics', 'hostinger-reach' ),
            'hostinger_reach_ui_tooltip_ctor_info'                        => __( 'Click-to-open rate tells you what percent of opens resulted in a click too. A good CTOR is 6-17%, depending on your industry.', 'hostinger-reach' ),
            'hostinger_reach_forms_modal_title'                           => __( 'Select page', 'hostinger-reach' ),
            'hostinger_reach_select_form_modal_title'                     => __( 'Select a form', 'hostinger-reach' ),
            'hostinger_reach_select_form_modal_subtitle'                  => __( 'Only active forms created with Reach are displayed below. Select one or create a new one.', 'hostinger-reach' ),
            'hostinger_reach_select_form_modal_search_placeholder'        => __( 'Search a template', 'hostinger-reach' ),
            'hostinger_reach_select_form_modal_no_results'                => __( 'No forms match your search.', 'hostinger-reach' ),
            'hostinger_reach_select_form_modal_create_new'                => __( 'Create a new form', 'hostinger-reach' ),
            'hostinger_reach_select_form_modal_refresh'                   => __( 'Refresh forms', 'hostinger-reach' ),
            'hostinger_reach_select_form_modal_cancel'                    => __( 'Cancel', 'hostinger-reach' ),
            'hostinger_reach_select_form_modal_continue'                  => __( 'Continue', 'hostinger-reach' ),
            'hostinger_reach_select_form_modal_empty_title'               => __( 'No forms yet', 'hostinger-reach' ),
            'hostinger_reach_select_form_modal_empty_subtitle'            => __( 'Build your first form in Hostinger Reach and add it to any page on your site.', 'hostinger-reach' ),
            'hostinger_reach_select_form_modal_empty_create'              => __( 'Create a form in Reach', 'hostinger-reach' ),
            'hostinger_reach_select_page_modal_title'                     => __( 'Select page for your form', 'hostinger-reach' ),
            'hostinger_reach_select_page_modal_selected_form_hint'        => __( 'Will be added to the selected page', 'hostinger-reach' ),
            'hostinger_reach_select_page_modal_change_selection'          => __( 'Change the selection', 'hostinger-reach' ),
            'hostinger_reach_select_page_modal_create_new_page'           => __( 'Create a new page', 'hostinger-reach' ),
            'hostinger_reach_select_page_modal_cancel'                    => __( 'Cancel', 'hostinger-reach' ),
            'hostinger_reach_select_page_modal_confirm'                   => __( 'Confirm', 'hostinger-reach' ),
            'hostinger_reach_add_form_modal_title'                        => __( 'Add form or plugin', 'hostinger-reach' ),
            'hostinger_reach_confirm_disconnect_modal_title'              => __( 'Disconnect plugin?', 'hostinger-reach' ),
            'hostinger_reach_confirm_disconnect_modal_text'               => __( 'Disconnecting will stop new contacts from being collected. You can reconnect or use a different form anytime.', 'hostinger-reach' ),
            'hostinger_reach_confirm_disconnect_modal_cancel'             => __( 'Cancel', 'hostinger-reach' ),
            'hostinger_reach_confirm_disconnect_modal_disconnect'         => __( 'Disconnect', 'hostinger-reach' ),
            'hostinger_reach_confirm_sync_modal_title'                    => __( 'Import contacts', 'hostinger-reach' ),
            'hostinger_reach_confirm_sync_modal_text'                     => __( 'Would you like to import contacts collected while sync was disabled?', 'hostinger-reach' ),
            'hostinger_reach_confirm_sync_modal_cancel'                   => __( 'Not needed', 'hostinger-reach' ),
            'hostinger_reach_confirm_sync_modal_confirm'                  => __( 'Sync', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_plugin_header'          => __( 'Plugin', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_contacts_header'        => __( 'Contacts', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_syncing_header'         => __( 'Forms syncing with Reach', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_syncing_tooltip'        => __( 'When syncing is off, collected Contacts will not be sent to Reach.', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_of'                     => __( 'of', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_status_header'          => __( 'Status', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_status_active'          => __( 'Active', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_status_inactive'        => __( 'Inactive', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_view_form'              => __( 'View form', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_edit_form'              => __( 'Edit form', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_go_to_plugin'           => __( 'Go to plugin', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_disconnect_plugin'      => __( 'Disconnect plugin', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_add_form'               => __( 'Add form', 'hostinger-reach' ),
            'hostinger_reach_plugin_titles_hostinger_reach'               => __( 'Hostinger Reach', 'hostinger-reach' ),
            'hostinger_reach_plugin_titles_contact_form_7'                => __( 'Contact Form 7', 'hostinger-reach' ),
            'hostinger_reach_plugin_titles_wp_forms_lite'                 => __( 'WP Forms Lite', 'hostinger-reach' ),
            'hostinger_reach_plugin_titles_elementor'                     => __( 'Elementor', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_expand_aria'            => __( 'Expand {pluginName} details', 'hostinger-reach' ),
            'hostinger_reach_plugin_entries_table_collapse_aria'          => __( 'Collapse {pluginName} details', 'hostinger-reach' ),
            'hostinger_reach_plugin_expansion_no_forms'                   => __( 'No forms found for this integration.', 'hostinger-reach' ),
            'hostinger_reach_forms_no_title'                              => __( '(no title)', 'hostinger-reach' ),
            'hostinger_reach_forms_plugin_connected_success'              => __( 'Plugin connected successfully', 'hostinger-reach' ),
            'hostinger_reach_forms_plugin_disconnected_success'           => __( 'Plugin disconnected successfully', 'hostinger-reach' ),
            'hostinger_reach_forms_active'                                => __( 'Active', 'hostinger-reach' ),
            'hostinger_reach_forms_consent_notice'                        => __( 'Make sure the people you contact expect your emails and are okay with receiving them.', 'hostinger-reach' ),
            'hostinger_reach_forms_new_contact_form'                      => __( 'New contact form', 'hostinger-reach' ),
            'hostinger_reach_forms_create_form_button'                    => __( 'Add form', 'hostinger-reach' ),
            'hostinger_reach_forms_supported_plugins'                     => __( 'Supported plugins', 'hostinger-reach' ),
            'hostinger_reach_forms_view_more_supported_plugins'           => __( 'View more supported plugins', 'hostinger-reach' ),
            'hostinger_reach_forms_installed_plugins'                     => __( 'Installed plugins', 'hostinger-reach' ),
            'hostinger_reach_forms_install'                               => __( 'Install', 'hostinger-reach' ),
            'hostinger_reach_forms_install_and_connect'                   => __( 'Install and connect', 'hostinger-reach' ),
            'hostinger_reach_forms_disconnect'                            => __( 'Disconnect', 'hostinger-reach' ),
            'hostinger_reach_forms_connect'                               => __( 'Connect', 'hostinger-reach' ),
            'hostinger_reach_sync_contacts_button_text'                   => __( 'Import Contacts', 'hostinger-reach' ),
            'hostinger_reach_contacts'                                    => __( 'Contacts', 'hostinger-reach' ),
            'hostinger_reach_contacts_modal_title'                        => __( 'Import your existing contacts to Reach', 'hostinger-reach' ),
            'hostinger_reach_contacts_modal_subtitle'                     => __( 'You can import existing contacts into Reach now to use them for your email campaigns. New contacts will be synced automatically to Reach.', 'hostinger-reach' ),
            'hostinger_reach_contacts_sync'                               => __( 'Import', 'hostinger-reach' ),
            'hostinger_reach_contacts_contacts_to_sync'                   => __( 'Contacts to Import', 'hostinger-reach' ),
            'hostinger_reach_contacts_contacts'                           => __( 'contacts', 'hostinger-reach' ),
            'hostinger_reach_contacts_info'                               => __( 'are ready to import to Reach. Any new contacts you’ll collect with these forms will sync automatically.', 'hostinger-reach' ),
            'hostinger_reach_contacts_none_selected'                      => __( 'You have not selected any forms to import. Select which forms you want to import and then click on the Import button below.', 'hostinger-reach' ),
            'hostinger_reach_contacts_not_available'                      => __( '-', 'hostinger-reach' ),
            'hostinger_reach_contacts_off'                                => __( 'Auto-sync off', 'hostinger-reach' ),
            'hostinger_reach_contacts_partially_imported'                 => __( 'Partially imported', 'hostinger-reach' ),
            'hostinger_reach_contacts_imported'                           => __( 'Imported', 'hostinger-reach' ),
            'hostinger_reach_contacts_not_imported'                       => __( 'Not imported', 'hostinger-reach' ),
            'hostinger_reach_contacts_importing'                          => __( 'Importing...', 'hostinger-reach' ),
            'hostinger_reach_contacts_import_success'                     => __( 'The selected contacts are being imported to Reach.', 'hostinger-reach' ),
            'hostinger_reach_contacts_import_error'                       => __( 'It was an error importing your contacts to Reach.', 'hostinger-reach' ),
            'hostinger_reach_add_form_snackbar_title'                     => __( "Don't see your plugin?", 'hostinger-reach' ),
            'hostinger_reach_add_form_snackbar_text'                      => __( 'It may not be supported yet. You can upload contacts as a CSV or use another plugin', 'hostinger-reach' ),
            'hostinger_reach_add_form_snackbar_link'                      => __( 'Upload as CSV', 'hostinger-reach' ),
            'hostinger_reach_overview_banner_label'                       => __( 'New', 'hostinger-reach' ),
            'hostinger_reach_overview_banner_title'                       => __( 'Form Builder', 'hostinger-reach' ),
            'hostinger_reach_overview_banner_description'                 => __( 'Create fully custom forms in Reach and easily embed them on your WordPress site using this plugin.', 'hostinger-reach' ),
            'hostinger_reach_overview_banner_button_text'                 => __( 'Create a Form', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_events'                          => __( 'Store Events', 'hostinger-reach' ),
            'hostinger_reach_woocommerce__status'                         => __( 'Status', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_checkout_title'                  => __( 'Checkout', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_checkout_description'            => __( 'Adds a marketing consent checkbox at checkout and syncs contacts to Reach.', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_order_purchased_title'           => __( 'Purchases', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_order_purchased_description'     => __( 'Tracks purchases so you can trigger email automations in Reach when enabled.', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_cart_abandoned_title'            => __( 'Abandoned carts', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_cart_abandoned_description'      => __( 'Tracks abandoned carts so you can trigger email automations in Reach when enabled.', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_automation'                      => __( 'Automation', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_manage_automations'              => __( 'Manage automations', 'hostinger-reach' ),
            'hostinger_reach_learn_more'                                  => __( 'Learn more', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_manage_plugin'                   => __( 'Manage plugin', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_connected_title'                 => __( 'WooCommerce Plugin Connected', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_connected_text'                  => __( 'If your store isn’t ready yet, complete the setup to start selling and collecting contacts', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_connected_button'                => __( 'Set up WooCommerce', 'hostinger-reach' ),
            'hostinger_reach_plugin_cannot_disable'                       => __( 'This form is a Reach native form inserted directly in your page. For disabling it remove the Form directly from the page using the editor.', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_subscribed_contacts_description' => __( 'Only subscribed contacts can receive automated emails. Customers must opt in to marketing and be synced to Reach before store automations can email them.', 'hostinger-reach' ),
            'hostinger_reach_woocommerce_view_contacts_link'              => __( 'View contacts', 'hostinger-reach' ),
            'hostinger_reach_connection_success_modal_title'              => __( 'is connected!', 'hostinger-reach' ),
            'hostinger_reach_connection_success_modal_subtitle'           => __( 'So how do you want to collect and sync contacts from your site to Reach?', 'hostinger-reach' ),
            'hostinger_reach_connection_success_modal_add_form'           => __( 'Add Reach form', 'hostinger-reach' ),
        );
    }
}
