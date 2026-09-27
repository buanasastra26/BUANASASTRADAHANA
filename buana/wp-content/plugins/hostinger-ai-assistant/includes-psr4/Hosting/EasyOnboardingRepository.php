<?php

namespace Hostinger\AiAssistant\Hosting;

use Hostinger\EasyOnboarding\Admin\Actions as EasyOnboardingActions;
use Hostinger\EasyOnboarding\Admin\Onboarding\Onboarding as EasyOnboardingSteps;
use Hostinger\EasyOnboarding\Helper as EasyOnboardingHelper;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class EasyOnboardingRepository {
    private const WOOCOMMERCE_PLUGIN = 'woocommerce/woocommerce.php';

    public function is_easy_onboarding_active(): bool {
        return class_exists( EasyOnboardingHelper::class );
    }

    public function is_woocommerce_active(): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( self::WOOCOMMERCE_PLUGIN );
    }

    public function is_store_setup_complete(): bool {
        if ( ! $this->is_easy_onboarding_active() ) {
            return false;
        }

        return ( new EasyOnboardingHelper() )->is_woocommerce_onboarding_completed();
    }

    public function is_search_console_step_complete(): bool {
        if ( ! class_exists( EasyOnboardingSteps::class ) || ! class_exists( EasyOnboardingActions::class ) ) {
            return false;
        }

        $steps = get_option( EasyOnboardingSteps::HOSTINGER_EASY_ONBOARDING_STEPS_OPTION_NAME, array() );

        if ( ! is_array( $steps ) ) {
            return false;
        }

        $category = EasyOnboardingSteps::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID;

        return ! empty( $steps[ $category ][ EasyOnboardingActions::GOOGLE_KIT ] );
    }
}
