<?php

namespace Hostinger\EasyOnboarding\Admin\Onboarding;

use Hostinger\EasyOnboarding\Admin\Actions;
use Hostinger\EasyOnboarding\Admin\Onboarding\Steps\Button;
use Hostinger\EasyOnboarding\Admin\Onboarding\Steps\Step;
use Hostinger\EasyOnboarding\Admin\Onboarding\Steps\StepCategory;
use Hostinger\EasyOnboarding\AmplitudeEvents\Actions as AmplitudeActions;
use Hostinger\EasyOnboarding\AmplitudeEvents\Amplitude;
use Hostinger\EasyOnboarding\Helper;

defined( 'ABSPATH' ) || exit;

class Onboarding {
    private const HOSTINGER_ADD_DOMAIN_PATH                         = '/add-domain/%s/select';
    private const HOSTINGER_EMAIL_UPSELL_PATH                       = '/emails/%s/choose-your-experience?location=%s';
    private const HOSTINGER_EMAIL_UPSELL_LOCATION                   = 'wordpress_easy_onboarding';
    public const HOSTINGER_EASY_ONBOARDING_STEPS_OPTION_NAME        = 'hostinger_easy_onboarding_steps';
    public const HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID = 'website_setup';
    public const HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID   = 'online_store_setup';
    /**
     * @var Helper
     */
    private Helper $helper;

    /**
     * @var array
     */
    private array $step_categories = array();

    /**
     * @return void
     */
    public function init(): void {
        $this->helper = new Helper();

        $this->load_step_categories();
    }

    /**
     * @return array
     */
    public function get_step_categories(): array {
        return array_map(
            function ( $item ) {
                return $item->to_array();
            },
            $this->step_categories
        );
    }

    /**
     * @param string $step_category_id
     * @param string $step_id
     *
     * @return bool
     */
    public function complete_step( string $step_category_id, string $step_id ): bool {
        if ( ! $this->validate_step( $step_category_id, $step_id ) ) {
            return false;
        }

        $onboarding_steps = $this->get_saved_steps();

        if ( empty( $onboarding_steps[ $step_category_id ] ) ) {
            $onboarding_steps[ $step_category_id ] = array();
        }

        $onboarding_steps[ $step_category_id ][ $step_id ] = true;

        $this->maybe_send_store_events( $onboarding_steps );

        $updated = update_option( self::HOSTINGER_EASY_ONBOARDING_STEPS_OPTION_NAME, $onboarding_steps, false );
        if ( $updated ) {
            do_action( 'hostinger_easy_onboarding_step_completed', $step_category_id, $step_id );
        }

        return $updated;
    }

    /**
     * @param string $step_category_id
     * @param string $step_id
     *
     * @return bool
     */
    public function validate_step( string $step_category_id, string $step_id ): bool {
        if ( $step_category_id === self::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID
            && in_array( $step_id, Actions::STORE_ACTIONS_LIST, true )
        ) {
            return true;
        }

        $step_categories = $this->get_step_categories();

        if ( empty( $step_categories ) ) {
            return false;
        }

        // Try to match step category id.
        $found = false;
        foreach ( $step_categories as $step_category ) {
            if ( $step_category['id'] === $step_category_id ) {
                if ( ! empty( $step_category['steps'] ) ) {
                    foreach ( $step_category['steps'] as $step ) {
                        if ( $step['id'] === $step_id ) {
                            $found = true;
                            break;
                        }
                    }
                }
                break;
            }
        }

        if ( empty( $found ) ) {
            return false;
        }

        return true;
    }

    /**
     * @param string $step_category_id
     * @param string $step_id
     *
     * @return bool
     */
    public function is_completed( string $step_category_id, string $step_id ): bool {
        $onboarding_steps = $this->get_saved_steps();

        if ( empty( $onboarding_steps[ $step_category_id ][ $step_id ] ) ) {
            return false;
        }

        return (bool) $onboarding_steps[ $step_category_id ][ $step_id ];
    }

    public function maybe_send_store_events( array $steps ): void {
        if ( $this->is_store_ready( $steps ) ) {
            $this->send_event( AmplitudeActions::WOO_READY_TO_SELL, true );
        }

        if ( $this->is_store_completed( $steps ) ) {
            $this->send_event( AmplitudeActions::WOO_SETUP_COMPLETED, true );
        }
    }

    public function get_first_step_data(): Step {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        $step = new Step( Actions::AI_STEP );

        $builder_type           = get_option( 'hostinger_builder_type' );
        $builder_type_whitelist = array( 'ai', 'theme', 'blank', 'prebuilt' );

        if ( ! in_array( $builder_type, $builder_type_whitelist, true ) ) {
            return $step;
        }

        $themes                = wp_get_themes();
        $is_ai_theme_installed = array_key_exists( 'hostinger-ai-theme', $themes );
        $is_ai_theme_active    = ( get_stylesheet() === 'hostinger-ai-theme' );
        $is_ai_theme_ready     = $is_ai_theme_installed && $is_ai_theme_active;
        $is_completed          = $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::AI_STEP );
        $is_ai_generated       = ! empty( get_option( 'hostinger_ai_created_pages' ) );

        if ( $is_ai_generated && ! $is_completed ) {
            $onboarding_steps = $this->get_saved_steps();
            if ( empty( $onboarding_steps[ self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID ] ) ) {
                $onboarding_steps[ self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID ] = array();
            }
            $onboarding_steps[ self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID ][ Actions::AI_STEP ] = true;
            update_option( self::HOSTINGER_EASY_ONBOARDING_STEPS_OPTION_NAME, $onboarding_steps, false );
            $is_completed = true;
        }

        $step->set_image_url( HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/images/steps/ai_step.svg' );
        $step->set_title( __( 'Create site with AI', 'hostinger-easy-onboarding' ) );
        $step->set_description( __( 'Use AI to build your website in minutes — just answer a few questions and we\'ll generate a site tailored to your needs.', 'hostinger-easy-onboarding' ) );

        if ( $is_completed && $is_ai_theme_ready ) {
            $primary_button = new Button();
            $primary_button->set_title( __( 'Create again', 'hostinger-easy-onboarding' ) );
            $primary_button->set_url( admin_url( 'admin.php?page=hostinger-ai-website-creation&redirect=hostinger-easy-onboarding' ) );
            $primary_button->set_is_completable( false );
            $step->set_primary_button( $primary_button );
        } elseif ( $is_completed ) {
            $primary_button = new Button();
            $primary_button->set_title( __( 'Create new site with AI', 'hostinger-easy-onboarding' ) );
            $primary_button->set_modal_name( 'CreateWebsiteWithAiBuilderModal' );
            $step->set_primary_button( $primary_button );
        } else {
            $primary_button = new Button();
            $primary_button->set_title( __( 'Create new site with AI', 'hostinger-easy-onboarding' ) );
            $primary_button->set_modal_name( 'CreateWebsiteWithAiBuilderModal' );

            $secondary_button = new Button();
            $secondary_button->set_title( __( 'Not needed', 'hostinger-easy-onboarding' ) );
            $secondary_button->set_is_skippable( true );

            $step->set_primary_button( $primary_button );
            $step->set_secondary_button( $secondary_button );
        }

        return $step;
    }

    public static function is_first_step_active(): bool {
        $builder_type = get_option( 'hostinger_builder_type' );

        $builder_type_whitelist = array(
            'ai',
            'theme',
            'blank',
            'prebuilt',
        );

        if ( ! in_array( $builder_type, $builder_type_whitelist, true ) ) {
            return false;
        }

        $hosting_plan = get_option( 'hostinger_hosting_plan', false );

        if ( empty( $hosting_plan ) ) {
            return false;
        }

        return true;
    }

    public function is_onboarding_completed_without_reach(): bool {
        $actions = Actions::get_action_list_without_reach();

        foreach ( $actions as $action_id => $action ) {
            if ( ! $this->is_completed( Onboarding::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, $action ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return void
     */
    private function load_step_categories(): void {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        $website_step_category = new StepCategory(
            self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID,
            __( 'Website setup', 'hostinger-easy-onboarding' )
        );

        $first_step = $this->get_first_step_data();

        if ( ! empty( $first_step->get_title() ) ) {
            $website_step_category->add_step( $first_step );
        }

        if ( is_plugin_active( 'hostinger-affiliate-plugin/hostinger-affiliate-plugin.php' ) ) {
            $website_step_category->add_step( $this->get_amazon_affiliate_step() );
        }

        if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
            $this->send_event( AmplitudeActions::WOO_INSTALLED, true );
            $website_step_category->add_step( $this->get_started_with_store() );
        }

        // Connect domain.
        $website_step_category->add_step( $this->get_add_domain_step() );

        $website_step_category->add_step( $this->get_claim_email_step() );

        $website_step_category->add_step( $this->get_plugins_step() );

        $website_step_category->add_step( $this->get_google_kit_step() );

        if ( Actions::is_enable_ai_discovery_step_eligible() ) {
            $website_step_category->add_step( $this->get_enable_ai_discovery_step() );
        }

        if ( $this->is_reach_eliglible() ) {
            $website_step_category->add_step( $this->get_reach_step() );
        }

        // Add category.
        $this->step_categories[] = $website_step_category;

        if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
            $store_step_category = new StepCategory(
                self::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID,
                __( 'Online store setup', 'hostinger-easy-onboarding' )
            );

            // Setup online store.
            $store_step_category->add_step( $this->get_setup_online_store() );

            // Add product.
            $store_step_category->add_step( $this->get_add_product_step() );

            // Add payment method.
            $store_step_category->add_step( $this->get_payment_method_step() );

            if ( ! $this->helper->is_selling_digital_products() ) {
                // Add shipping method.
                $store_step_category->add_step( $this->get_shipping_method_step() );
            }

            $this->step_categories[] = $store_step_category;
        }
    }

    private function send_event( string $action, bool $once = false ): bool {
        if ( $once ) {
            $option_name = 'hostinger_amplitude_' . $action;

            $event_sent = get_option( $option_name, false );

            if ( $event_sent ) {
                return false;
            }
        }

        $should_send_event = true;

        if ( $once ) {
            $should_send_event = add_option( $option_name, true );
        }

        if ( ! $should_send_event ) {
            return false;
        }

        $amplitude = new Amplitude();
        $params    = array( 'action' => $action );
        $event     = $amplitude->send_event( $params );

        return ! empty( $event );
    }

    private function is_store_completed( $steps ): bool {
        $all_woo_steps       = Actions::get_category_action_lists()[ Onboarding::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID ];
        $completed_woo_steps = ! empty( $steps[ Onboarding::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID ] ) ? $steps[ Onboarding::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID ] : array();

        foreach ( $all_woo_steps as $step_key ) {
            if ( empty( $completed_woo_steps[ $step_key ] ) ) {
                return false;
            }
        }

        return true;
    }

    private function is_store_ready( array $steps ): bool {
        $store_steps = $steps[ Onboarding::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID ] ?? array();

        return ! empty( $store_steps[ Actions::ADD_PAYMENT ] ) && ! empty( $store_steps[ Actions::ADD_PRODUCT ] );
    }

    private function get_plugins_step(): Step {
        $step = new Step( Actions::PLUGINS );

        $step->set_image_url( HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/images/steps/install_plugins.svg' );

        $step->set_title( __( 'Enhance your website with plugins', 'hostinger-easy-onboarding' ) );

        $step->set_description( __( 'Install recommended plugins to add powerful features to your WordPress site.', 'hostinger-easy-onboarding' ) );

        $primary_button = new Button( __( 'Install plugins', 'hostinger-easy-onboarding' ) );
        $primary_button->set_modal_name( 'InstallPluginsModal' );

        $step->set_primary_button( $primary_button );

        if ( ! $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::PLUGINS ) ) {
            $secondary_button = new Button( __( 'Not needed', 'hostinger-easy-onboarding' ) );
            $secondary_button->set_is_skippable( true );

            $step->set_secondary_button( $secondary_button );
        }

        return $step;
    }

    private function get_reach_step(): Step {
        $step = new Step( Actions::REACH );

        $step->set_image_url( HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/images/steps/hostinger_reach.svg' );

        $step->set_title( __( 'Turn site visitors into subscribers', 'hostinger-easy-onboarding' ) );

        $step->set_description( __( 'Collect emails from forms on your site and start sending on-brand email campaigns with Hostinger Reach – all powered by AI.', 'hostinger-easy-onboarding' ) );

        $primary_button = new Button( __( 'Connect to Reach', 'hostinger-easy-onboarding' ) );

        $primary_button->set_url( admin_url( 'admin.php?page=hostinger-reach' ) );

        $secondary_button = new Button( __( 'Not needed', 'hostinger-easy-onboarding' ) );

        $secondary_button->set_is_skippable( true );

        if ( ! $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::REACH ) ) {
            $step->set_primary_button( $primary_button );
            $step->set_secondary_button( $secondary_button );
        }

        return $step;
    }


    /**
     * @return array
     */
    private function get_saved_steps(): array {
        return get_option( self::HOSTINGER_EASY_ONBOARDING_STEPS_OPTION_NAME, array() );
    }

    private function get_add_domain_step(): Step {
        $step = new Step( Actions::DOMAIN_IS_CONNECTED );

        $step->set_image_url( HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/images/steps/connect_domain.svg' );

        $step->set_title( __( 'Add a domain', 'hostinger-easy-onboarding' ) );

        $button = new Button( __( 'Add domain', 'hostinger-easy-onboarding' ) );

        if ( $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::DOMAIN_IS_CONNECTED ) ) {
            $step->set_title( __( 'Connected a domain', 'hostinger-easy-onboarding' ) );
        }

        $step->set_description(
            __(
                'Every website needs a domain that makes it easy to access and remember. Get yours in just a few clicks.',
                'hostinger-easy-onboarding'
            )
        );

        $site_url = preg_replace( '#^https?://#', '', get_site_url() );

        $query_parameters = array(
            'websiteType' => 'wordpress',
            'redirectUrl' => $this->helper->get_hpanel_url( Helper::HPANEL_WEBSITES_PATH ),
        );

        $ai_domain_query = $this->helper->get_ai_domain_query();

        if ( $ai_domain_query !== '' ) {
            $query_parameters['description'] = $ai_domain_query;
        }

        $add_domain_url = $this->helper->get_hpanel_url( sprintf( self::HOSTINGER_ADD_DOMAIN_PATH, $site_url ) );

        $button->set_url( $add_domain_url . '?' . http_build_query( $query_parameters ) );

        if ( ! $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::DOMAIN_IS_CONNECTED ) ) {
            $step->set_primary_button( $button );
        }

        return $step;
    }

    private function get_amazon_affiliate_step(): Step {
        $step = new Step( Actions::AMAZON_AFFILIATE );

        $step->set_image_url( HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/images/steps/amazon_affiliate.svg' );

        if ( get_locale() === 'pt_BR' ) {
            $step->set_title( __( 'Promote products on your site', 'hostinger-easy-onboarding' ) );

            $step->set_description( __( 'Start promoting affiliate products and earn rewards. It’s quick and easy to get started.', 'hostinger-easy-onboarding' ) );

            $button = new Button( __( 'Start promoting', 'hostinger-easy-onboarding' ) );
        } else {
            $step->set_title( __( 'Connect your affiliate account', 'hostinger-easy-onboarding' ) );

            $step->set_description( __( 'Link your Amazon or Mercado Livre affiliate account in just a few clicks, start promoting products, and earn rewards.', 'hostinger-easy-onboarding' ) );

            $button = new Button( __( 'Connect affiliate account', 'hostinger-easy-onboarding' ) );
        }

        $button->set_url( admin_url( 'admin.php?page=hostinger-amazon-affiliate' ) );

        if ( ! $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::AMAZON_AFFILIATE ) ) {
            $step->set_primary_button( $button );
        }

        return $step;
    }

    private function get_started_with_store(): Step {
        $step = new Step( Actions::STORE_TASKS );

        $step->set_image_url( HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/images/steps/store_tasks.svg' );

        $step->set_title( __( 'Set up your online store', 'hostinger-easy-onboarding' ) );

        $step->set_description( __( 'Get ready to sell online. Add your first product, then set up shipping and payments.', 'hostinger-easy-onboarding' ) );

        $primary_button = new Button( __( 'Get started', 'hostinger-easy-onboarding' ) );

        $primary_button->set_url( admin_url( 'admin.php?page=hostinger-get-onboarding' ) );

        if ( $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::STORE_TASKS ) ) {
            $primary_button->set_title( __( 'View list', 'hostinger-easy-onboarding' ) );
        }

        if ( ! $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::STORE_TASKS ) ) {
            $step->set_primary_button( $primary_button );
        }

        return $step;
    }

    private function get_enable_ai_discovery_step(): Step {
        $step = new Step( Actions::ENABLE_AI_DISCOVERY );

        $step->set_image_url( HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/images/steps/ai_discovery.svg' );

        $step->set_title( __( 'Make your site discoverable by AI', 'hostinger-easy-onboarding' ) );

        $step->set_description( __( 'Let AI explore, understand, and interact with your site. All content updates are auto-tracked so AI tools stay in sync with your site’s latest version.', 'hostinger-easy-onboarding' ) );

        $primary_button = new Button( __( 'Turn on', 'hostinger-easy-onboarding' ) );
        $primary_button->set_url( admin_url( 'admin.php?page=hostinger-get-onboarding' ) );

        $secondary_button = new Button( __( 'Not needed', 'hostinger-easy-onboarding' ) );
        $secondary_button->set_is_skippable( true );

        if ( ! $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::ENABLE_AI_DISCOVERY ) ) {
            $step->set_primary_button( $primary_button );
            $step->set_secondary_button( $secondary_button );
        }

        return $step;
    }

    private function get_setup_online_store(): Step {
        $step = new Step( Actions::SETUP_STORE );

        $step->set_image_url( HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/images/steps/store_tasks.svg' );

        $step->set_title( __( 'Add store details', 'hostinger-easy-onboarding' ) );

        $step->set_description( __( 'We\'ll use this information to help you set up your store faster.', 'hostinger-easy-onboarding' ) );

        $primary_button = new Button( __( 'Get started', 'hostinger-easy-onboarding' ) );

        if ( $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID, Actions::SETUP_STORE ) ) {
            $primary_button->set_url( admin_url( 'admin.php?page=wc-settings' ) );
        } else {
            $primary_button->set_modal_name( 'SetupOnlineStoreModal' );
        }

        if ( ! $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID, Actions::SETUP_STORE ) ) {
            $step->set_primary_button( $primary_button );
        }

        return $step;
    }

    private function get_add_product_step(): Step {
        $step = new Step( Actions::ADD_PRODUCT );

        $step->set_image_url( HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/images/steps/add_product.svg' );

        $step->set_title( __( 'Add your first product or service', 'hostinger-easy-onboarding' ) );

        $step->set_description( __( 'Sell products, services and digital downloads. Set up and customize each item to fit your business needs.', 'hostinger-easy-onboarding' ) );

        $primary_button = new Button( __( 'Add product', 'hostinger-easy-onboarding' ) );

        $primary_button->set_url( admin_url( 'post-new.php?post_type=product' ) );

        $secondary_button = new Button( __( 'Not now', 'hostinger-easy-onboarding' ) );

        $secondary_button->set_is_skippable( true );

        if ( ! $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID, Actions::ADD_PRODUCT ) ) {
            $step->set_primary_button( $primary_button );
            $step->set_secondary_button( $secondary_button );
        }

        return $step;
    }

    private function get_payment_method_step(): Step {
        $step = new Step( Actions::ADD_PAYMENT );

        $step->set_image_url( HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/images/steps/add_payment_method.svg' );

        $step->set_title( __( 'Set up payments', 'hostinger-easy-onboarding' ) );

        $step->set_description( __( 'Get ready to sell online. Add your first product, then set up payments and shipping.', 'hostinger-easy-onboarding' ) );

        $primary_button = new Button( __( 'Set up payment method', 'hostinger-easy-onboarding' ) );

        $primary_button->set_modal_name( 'SetupPaymentMethodModal' );

        $primary_button->set_url( admin_url( 'admin.php?page=hostinger-get-onboarding&subPage=hostinger-store-add-payment-method' ) );

        if ( ! $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID, Actions::ADD_PAYMENT ) ) {
            $step->set_primary_button( $primary_button );
        }

        return $step;
    }

    private function get_shipping_method_step(): Step {
        $step = new Step( Actions::ADD_SHIPPING );

        $step->set_image_url( HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/images/steps/add_shipping_method.svg' );

        $step->set_title( __( 'Manage shipping', 'hostinger-easy-onboarding' ) );

        $step->set_description( __( 'Choose the ways you\'d like to ship orders to customers. You can always add others later.', 'hostinger-easy-onboarding' ) );

        $primary_button = new Button( __( 'Set up shipping method', 'hostinger-easy-onboarding' ) );

        $primary_button->set_modal_name( 'SetupShippingMethodModal' );

        $secondary_button = new Button( __( 'Not needed', 'hostinger-easy-onboarding' ) );

        $secondary_button->set_is_skippable( true );

        if ( ! $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID, Actions::ADD_SHIPPING ) ) {
            $step->set_primary_button( $primary_button );
            $step->set_secondary_button( $secondary_button );
        }

        return $step;
    }

    private function get_claim_email_step(): Step {
        $step = new Step( Actions::CLAIM_EMAIL );

        $step->set_image_url( HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/images/steps/claim_email.svg' );

        $step->set_title( __( 'Claim a free business email', 'hostinger-easy-onboarding' ) );

        $step->set_description( __( 'Set up professional email to build trust with your customers.', 'hostinger-easy-onboarding' ) );

        if ( $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::CLAIM_EMAIL ) ) {
            return $step;
        }

        $domain = preg_replace( '#^https?://#', '', (string) get_option( 'siteurl' ) );

        $upsell_email_url = $this->helper->get_hpanel_url(
            sprintf(
                self::HOSTINGER_EMAIL_UPSELL_PATH,
                rawurlencode( (string) $domain ),
                self::HOSTINGER_EMAIL_UPSELL_LOCATION
            )
        );

        $primary_button = new Button( __( 'Claim now', 'hostinger-easy-onboarding' ) );
        $primary_button->set_url( '#' );
        $primary_button->set_encoded_url( base64_encode( $upsell_email_url ) );

        $secondary_button = new Button( __( 'Not needed', 'hostinger-easy-onboarding' ) );
        $secondary_button->set_is_skippable( true );

        if ( $this->helper->is_free_subdomain() || strpos( $domain, 'hostingersite.com' ) !== false ) {
            $step->set_error_message( __( 'Connect your domain first', 'hostinger-easy-onboarding' ) );
            $primary_button->set_title( '' );
            $secondary_button->set_title( '' );
            $primary_button->set_encoded_url( '' );
        }

        $step->set_primary_button( $primary_button );
        $step->set_secondary_button( $secondary_button );

        return $step;
    }

    private function get_google_kit_step(): Step {
        $step = new Step( Actions::GOOGLE_KIT );

        $step->set_image_url( HOSTINGER_EASY_ONBOARDING_ASSETS_URL . '/images/steps/google_kit.svg' );

        $step->set_title( __( 'Get found on Google', 'hostinger-easy-onboarding' ) );

        $step->set_description( __( 'Make sure that your website shows up when visitors are looking for your business on Google.', 'hostinger-easy-onboarding' ) );

        $primary_button = new Button( __( 'Set up Google Site Kit', 'hostinger-easy-onboarding' ) );

        $primary_button->set_url( admin_url( 'admin.php?page=googlesitekit-splash' ) );

        if ( $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::GOOGLE_KIT ) ) {
            $primary_button->set_title( __( 'Manage', 'hostinger-easy-onboarding' ) );
        } else {
            $primary_button->set_modal_name( 'SkipGoogleSiteKitModal' );
        }

        $secondary_button = new Button( __( 'Not needed', 'hostinger-easy-onboarding' ) );

        $secondary_button->set_is_skippable( true );

        if ( ! $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::DOMAIN_IS_CONNECTED ) ) {
            $step->set_error_message( __( 'Connect your domain first', 'hostinger-easy-onboarding' ) );
            $primary_button->set_title( '' );
            $secondary_button->set_title( '' );

            $step->set_secondary_button( $secondary_button );
        }

        if ( ! $this->is_completed( self::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::GOOGLE_KIT ) ) {
            $step->set_primary_button( $primary_button );
        }

        return $step;
    }

    private function is_reach_eliglible(): bool {
        $onboarding_steps_was_completed = get_option( 'hostinger_onboarding_steps_was_completed', null );
        if ( $onboarding_steps_was_completed ) {
            return false;
        }

        return true;
    }
}
