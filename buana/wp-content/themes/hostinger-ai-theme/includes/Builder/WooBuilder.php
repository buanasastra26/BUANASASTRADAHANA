<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Constants\GenerationConstant;
use Hostinger\AiTheme\Constants\PreviewImageConstant;
use Hostinger\EasyOnboarding\Dto\WooSetupParameters;
use Hostinger\EasyOnboarding\WooCommerce\SetupHandler;
use WC_Countries;

defined( 'ABSPATH' ) || exit;

class WooBuilder extends AbstractPluginBuilder {
    private const PLUGIN_FILE = 'woocommerce/woocommerce.php';
    private const PLUGIN_NAME = 'WooCommerce';
    private const DOWNLOAD_URL = 'https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip';

    /**
     * Central state/region codes for countries that require a state in WooCommerce.
     * Falls back to first available state if country not listed here.
     */
    private const COUNTRY_CENTRAL_STATES = [
        'AL' => 'AL-11',     // Albania - Tirana.
        'DZ' => 'DZ-16',     // Algeria - Algiers.
        'AO' => 'LUA',       // Angola - Luanda.
        'AR' => 'C',         // Argentina - Buenos Aires City.
        'AU' => 'ACT',       // Australia - Australian Capital Territory (Canberra).
        'BD' => 'BD-13',     // Bangladesh - Dhaka.
        'BJ' => 'OU',        // Benin - Ouémé (Porto-Novo).
        'BO' => 'BO-L',      // Bolivia - La Paz.
        'BR' => 'DF',        // Brazil - Distrito Federal (Brasília).
        'BG' => 'BG-22',     // Bulgaria - Sofia.
        'CA' => 'ON',        // Canada - Ontario (Ottawa).
        'CL' => 'CL-RM',     // Chile - Región Metropolitana de Santiago.
        'CN' => 'CN2',       // China - Beijing.
        'CO' => 'CO-DC',     // Colombia - Capital District (Bogotá).
        'CR' => 'CR-SJ',     // Costa Rica - San José.
        'HR' => 'HR-21',     // Croatia - Zagreb City.
        'DO' => 'DO-01',     // Dominican Republic - Distrito Nacional (Santo Domingo).
        'EC' => 'EC-P',      // Ecuador - Pichincha (Quito).
        'EG' => 'EGC',       // Egypt - Cairo.
        'SV' => 'SV-SS',     // El Salvador - San Salvador.
        'DE' => 'DE-BE',     // Germany - Berlin.
        'GH' => 'AA',        // Ghana - Greater Accra.
        'GR' => 'I',         // Greece - Attica (Athens).
        'GT' => 'GT-GU',     // Guatemala - Guatemala.
        'HN' => 'HN-FM',     // Honduras - Francisco Morazán (Tegucigalpa).
        'HK' => 'HONG KONG', // Hong Kong - Hong Kong Island.
        'HU' => 'BU',        // Hungary - Budapest.
        'IN' => 'DL',        // India - Delhi.
        'ID' => 'JK',        // Indonesia - DKI Jakarta.
        'IR' => 'THR',       // Iran - Tehran.
        'IE' => 'D',         // Ireland - Dublin.
        'IT' => 'RM',        // Italy - Roma.
        'JM' => 'JM-01',     // Jamaica - Kingston.
        'JP' => 'JP13',      // Japan - Tokyo.
        'KE' => 'KE30',      // Kenya - Nairobi County.
        'KN' => 'KN03',      // Saint Kitts and Nevis - Saint George Basseterre.
        'LA' => 'VT',        // Laos - Vientiane.
        'LR' => 'MO',        // Liberia - Montserrado (Monrovia).
        'MY' => 'KUL',       // Malaysia - Kuala Lumpur.
        'MX' => 'DF',        // Mexico - Ciudad de México.
        'MD' => 'C',         // Moldova - Chișinău.
        'MA' => 'marab',     // Morocco - Rabat.
        'MZ' => 'MZMPM',     // Mozambique - Maputo.
        'NA' => 'KH',        // Namibia - Khomas (Windhoek).
        'NP' => 'BAG',       // Nepal - Bagmati (Kathmandu).
        'NZ' => 'WGN',       // New Zealand - Wellington.
        'NI' => 'NI-MN',     // Nicaragua - Managua.
        'NG' => 'FC',        // Nigeria - Abuja (Federal Capital).
        'PK' => 'IS',        // Pakistan - Islamabad Capital Territory.
        'PA' => 'PA-8',      // Panama - Panamá.
        'PY' => 'PY-ASU',    // Paraguay - Asunción.
        'PE' => 'LIM',       // Peru - Lima.
        'PH' => '00',        // Philippines - Metro Manila.
        'RO' => 'B',         // Romania - București.
        'SN' => 'SNDK',      // Senegal - Dakar.
        'RS' => 'RS00',      // Serbia - Belgrade.
        'ZA' => 'GP',        // South Africa - Gauteng (Pretoria).
        'ES' => 'M',         // Spain - Madrid.
        'TZ' => 'TZ03',      // Tanzania - Dodoma.
        'TH' => 'TH-10',     // Thailand - Bangkok.
        'TR' => 'TR06',      // Türkiye - Ankara.
        'UG' => 'UG102',     // Uganda - Kampala.
        'UA' => 'UA30',      // Ukraine - Kyiv.
        'US' => 'DC',        // United States - District of Columbia (Washington D.C.).
        'UY' => 'UY-MO',     // Uruguay - Montevideo.
        'VE' => 'VE-A',      // Venezuela - Capital (Caracas).
        'ZM' => 'ZM-09',     // Zambia - Lusaka.
    ];

    private const PRODUCT_RETRY_DELAYS_MS = [ 0, 500, 1000 ];

    private ImageManager|null $image_manager;

    public function __construct( ImageManager $image_manager = null ) {
        $this->image_manager = $image_manager;
    }

    protected function get_plugin_file(): string {
        return self::PLUGIN_FILE;
    }

    protected function get_plugin_name(): string {
        return self::PLUGIN_NAME;
    }

    protected function get_download_url(): string {
        return self::DOWNLOAD_URL;
    }

    protected function get_error_code(): string {
        return 'woocommerce';
    }

    protected function is_enabled(): bool {
        return get_option( 'hostinger_ai_woo', false );
    }

    protected function after_activation(): void {
        $this->setup_localization();
    }

    private function setup_localization(): void {
        if ( ! class_exists( 'Hostinger\EasyOnboarding\WooCommerce\SetupHandler' ) ) {
            return;
        }

        $parameters = array(
            'store_name' => get_option( 'hostinger_ai_brand_name', '' ),
            'industry' => '',
            'store_location' => $this->get_store_location(),
            'business_email' => get_option( 'admin_email' ),
        );

        $woo_parameters = WooSetupParameters::from_array( $parameters );
        $setup_handler = new SetupHandler( $woo_parameters );
        $setup_handler->setup();

        update_option( 'woocommerce_coming_soon', 'no' );
    }

    private function get_store_location(): string {
        $country = get_option( 'hostinger_country', 'US' );
        if ( ! class_exists( 'WC_Countries' ) ) {
            return $country;
        }

        $wc_countries = new WC_Countries();
        $states       = $wc_countries->get_states( $country );
        if ( empty( $states ) ) {
            return $country;
        }

        $state = self::COUNTRY_CENTRAL_STATES[ $country ] ?? array_key_first( $states );
        if ( ! isset( $states[ $state ] ) ) {
            $state = array_key_first( $states );
        }

        return $country . ':' . $state;
    }

    public function generate_products( array $content ): bool {
        $total_attempts = count( self::PRODUCT_RETRY_DELAYS_MS );

        foreach ( self::PRODUCT_RETRY_DELAYS_MS as $index => $delay_ms ) {
            if ( $delay_ms > 0 ) {
                usleep( $delay_ms * 1000 );
            }

            $product_ids = $this->attempt_product_generation( $content );

            if ( ! empty( $product_ids ) ) {
                update_option( 'hostinger_ai_created_products', $product_ids );
                return true;
            }

            Helper::log( sprintf( 'Product generation attempt %d produced 0 products', $index + 1 ) );
        }

        Helper::log( sprintf( 'Product generation failed after %d attempts — continuing without products', $total_attempts ) );

        return false;
    }

    protected function attempt_product_generation( array $content ): array {
        if ( empty( $content['pages']['ecommercePagesGroup']['sections'] ) ) {
            return [];
        }

        if ( ! function_exists( 'wc_get_product' ) ) {
            return [];
        }

        $created_product_ids = [];
        $category_ids        = ( new ProductCategoryManager() )->ensure_category_ids();

        foreach ( $content['pages']['ecommercePagesGroup']['sections'] as $section ) {
            if ( empty( $section['type'] ) || empty( $section['elements'] ) ) {
                continue;
            }

            $products = $section['type'] === 'products'
                ? $this->group_product_elements( $section['elements'] )
                : ( $section['type'] === 'product'
                    ? array( $this->extract_product_data( $section['elements'] ) )
                    : array() );

            foreach ( $products as $product_data ) {
                if ( empty( $product_data['title'] ) ) {
                    continue;
                }

                $product_id = $this->create_product( $product_data );

                if ( $product_id ) {
                    $created_product_ids[] = $product_id;
                    $this->assign_product_category( $product_id, $category_ids, count( $created_product_ids ) - 1 );
                }
            }
        }

        return $created_product_ids;
    }

    private function group_product_elements( array $elements ): array {
        $products = array();

        foreach ( $elements as $key => $element ) {
            if ( empty( $element['type'] ) || empty( $element['content'] ) ) {
                continue;
            }

            if ( ! preg_match( '/^product_(\d+)_/', $key, $matches ) ) {
                continue;
            }

            $product_num = (int) $matches[1];

            if ( ! isset( $products[ $product_num ] ) ) {
                $products[ $product_num ] = array(
                    'title'       => '',
                    'description' => '',
                    'price'       => '',
                    'image'       => '',
                );
            }

            $this->assign_element_to_product( $products[ $product_num ], $element );
        }

        return $products;
    }

    private function extract_product_data( array $elements ): array {
        $product_data = array(
            'title'       => '',
            'description' => '',
            'price'       => '',
            'image'       => '',
        );

        foreach ( $elements as $element ) {
            if ( empty( $element['type'] ) || empty( $element['content'] ) ) {
                continue;
            }

            $this->assign_element_to_product( $product_data, $element );
        }

        return $product_data;
    }

    private function assign_element_to_product( array &$product_data, array $element ): void {
        switch ( $element['type'] ) {
            case 'Title':
                $product_data['title'] = sanitize_text_field( $element['content'] );
                break;
            case 'Description':
                $product_data['description'] = wp_kses_post( $element['content'] );
                break;
            case 'Price number':
                $price                  = preg_replace( '/[^0-9.]/', '', $element['content'] );
                $product_data['price'] = floatval( $price );
                break;
            case 'Image':
                $product_data['image'] = sanitize_text_field( $element['content'] );
                break;
        }
    }

    private function create_product( array $product_data ): int|false {
        $new_product = [
            'post_title'    => $product_data['title'],
            'post_content'  => $product_data['description'],
            'post_status'   => 'publish',
            'post_type'     => 'product',
            'meta_input'    => array(
                GenerationConstant::META_KEY => '1',
            ),
        ];

        $product_id = wp_insert_post( $new_product );

        if ( is_wp_error( $product_id ) ) {
            return false;
        }

        if ( ! empty( $product_data['price'] ) ) {
            update_post_meta( $product_id, '_price', $product_data['price'] );
            update_post_meta( $product_id, '_regular_price', $product_data['price'] );
        }

        if ( ! empty( $product_data['image'] ) ) {
            $this->image_manager->set_keyword( $product_data['image'] );
            $image_data = $this->image_manager->get_unsplash_image_data( true );

            if ( ! empty( get_object_vars( $image_data ) ) ) {
                update_post_meta( $product_id, PreviewImageConstant::META_SLUG, $image_data->image );
                $this->image_manager->create_image_placeholder_attachment( $product_id, true );
            }
        }

        wp_set_object_terms( $product_id, 'simple', 'product_type' );

        return $product_id;
    }

    private function assign_product_category( int $product_id, array $category_ids, int $index ): void {
        if ( empty( $category_ids ) ) {
            return;
        }

        $category_id = $category_ids[ $index % count( $category_ids ) ];
        wp_set_object_terms( $product_id, array( (int) $category_id ), 'product_cat', false );
    }
}
