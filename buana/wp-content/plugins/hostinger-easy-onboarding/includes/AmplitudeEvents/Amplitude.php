<?php

namespace Hostinger\EasyOnboarding\AmplitudeEvents;

defined( 'ABSPATH' ) || exit;

use Hostinger\Amplitude\AmplitudeManager;
use Hostinger\EasyOnboarding\AmplitudeEvents\Actions as AmplitudeActions;
use Hostinger\WpHelper\Utils as Helper;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Constants;

class Amplitude {

    public const WEBSITE_BUILDER_TYPE = 'ai';
    /**
     * @var Helper
     */
    private Helper $helper;

    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var Config
     */
    private Config $config_handler;

    private array $options = array();

    public function __construct() {
        $this->helper                     = new Helper();
        $this->config_handler             = new Config();
        $this->client                     = new Client(
            $this->config_handler->getConfigValue( 'base_rest_uri', Constants::HOSTINGER_REST_URI ),
            array(
                Config::TOKEN_HEADER  => Helper::getApiToken(),
                Config::DOMAIN_HEADER => $this->helper->getHostInfo(),
            )
        );
        $this->options['builder_type']    = get_option( 'hostinger_builder_type', '' );
        $this->options['website_id']      = get_option( 'hostinger_website_id', '' );
        $this->options['subscription_id'] = get_option( 'hostinger_subscription_id', '' );
        $this->options['ai_version']      = get_option( 'hostinger_ai_version', '' );
    }

    /**
     * @param $params
     *
     * @return array
     */
    public function send_event( array $params ): array {
        $amplitude_manager = new AmplitudeManager( $this->helper, $this->config_handler, $this->client );

        return $amplitude_manager->sendRequest( $amplitude_manager::AMPLITUDE_ENDPOINT, $params );
    }

    public function send_edit_amplitude_event(): void {
        $edit_count = $this->increment_amplitude_edit_event_count();

        $params = array(
            'action'          => AmplitudeActions::WP_EDIT,
            'wp_builder_type' => $this->options['builder_type'],
            'website_id'      => $this->options['website_id'],
            'subscription_id' => $this->options['subscription_id'],
            'edit_count'      => $edit_count,
        );

        $this->send_event( $params );
    }

    public function increment_amplitude_edit_event_count(): int {
        $current    = (int) get_option( 'hostinger_amplitude_edit_count', 0 );
        $edit_count = $current + 1;

        if ( ! update_option( 'hostinger_amplitude_edit_count', $edit_count ) ) {
            return $current;
        }

        return $edit_count;
    }

    public function can_send_edit_amplitude_event(): bool {
        $is_ai_website_not_generated = ! $this->options['ai_version'];

        if ( ! $this->options['builder_type'] || ! $this->options['website_id'] || ! $this->options['subscription_id'] ) {
            return false;
        }

        if ( $this->options['builder_type'] === self::WEBSITE_BUILDER_TYPE && $is_ai_website_not_generated ) {
            return false;
        }

        return true;
    }
}
