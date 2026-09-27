<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils;

defined( 'ABSPATH' ) || exit;

class BuilderFactory {
    private function default_headers(): array {
        $helper = new Utils();

        return array(
            Config::TOKEN_HEADER  => Utils::getApiToken(),
            Config::DOMAIN_HEADER => $helper->getHostInfo(),
            'Content-Type'        => 'application/json',
        );
    }

    public function create_request_client(): RequestClient {
        $config = new Config();

        return new RequestClient(
            new Client(
                $config->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI ),
                $this->default_headers()
            )
        );
    }

    public function create_wh_api_client(): RequestClient {
        $config = new Config();

        return new RequestClient(
            new Client(
                $config->getConfigValue( 'base_proxy_rest_uri', HOSTINGER_WP_PROXY_API_URI ),
                $this->default_headers()
            )
        );
    }

    public function create_website_builder(): WebsiteBuilder {
        $image_manager = new ImageManager();

        $website_builder = new WebsiteBuilder(
            $this->create_request_client(),
            $image_manager,
            new AffiliateBuilder(),
            new ElementorBuilder(),
            new WooBuilder( $image_manager ),
            new HostingerReachBuilder(),
            new HostingerEcommerceBuilder(),
            new Fonts(),
            $this->create_wh_api_client()
        );

        $website_builder->init();

        return $website_builder;
    }
}
