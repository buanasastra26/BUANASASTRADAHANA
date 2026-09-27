<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Data\HostingerEcommerceHelper;

defined( 'ABSPATH' ) || exit;

class HostingerEcommerceBuilder extends AbstractPluginBuilder {
    use HostingerPluginUpdateUri;

    private const PLUGIN_FILE = 'hostinger-ecommerce-plugin/hostinger-ecommerce-plugin.php';
    private const PLUGIN_NAME = 'Hostinger Ecommerce';
    public const PLUGIN_SLUG  = 'hostinger-ecommerce-plugin';

    public const PRODUCTS_SHORTCODE       = '[hostinger_ecommerce_products]';
    public const PRODUCTS_BLOCK           = '<!-- wp:hostinger/products-list /-->';
    public const PRODUCTS_ELEMENTOR_WIDGET = 'hostinger-ecommerce-products-list';
    public const SHOP_ELEMENTOR_TEMPLATE  = 'hostinger-ecommerce-shop.json';

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
        return 'hostinger_ecommerce';
    }

    protected function is_enabled(): bool {
        return HostingerEcommerceHelper::is_active();
    }
}
