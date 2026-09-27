<?php

namespace Hostinger\AiAssistant\Hosting;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}


class HpanelUrlBuilder {

    private const HPANEL_ADD_DOMAIN_URL = 'https://hpanel.hostinger.com/add-domain/';
    private const HPANEL_WEBSITES_URL   = 'https://hpanel.hostinger.com/websites';

    private const HOSTINGER_LOCALES = array(
        'lt_LT' => 'hostinger.lt',
        'uk_UA' => 'hostinger.com.ua',
        'id_ID' => 'hostinger.co.id',
        'en_US' => 'hostinger.com',
        'es_ES' => 'hostinger.es',
        'es_AR' => 'hostinger.com.ar',
        'es_MX' => 'hostinger.mx',
        'es_CO' => 'hostinger.co',
        'pt_BR' => 'hostinger.com.br',
        'ro_RO' => 'hostinger.ro',
        'fr_FR' => 'hostinger.fr',
        'it_IT' => 'hostinger.it',
        'pl_PL' => 'hostinger.pl',
        'en_PH' => 'hostinger.ph',
        'ar_AE' => 'hostinger.ae',
        'ms_MY' => 'hostinger.my',
        'ko_KR' => 'hostinger.kr',
        'vi_VN' => 'hostinger.vn',
        'th_TH' => 'hostinger.in.th',
        'tr_TR' => 'hostinger.web.tr',
        'pt_PT' => 'hostinger.pt',
        'de_DE' => 'hostinger.de',
        'en_IN' => 'hostinger.in',
        'ja_JP' => 'hostinger.jp',
        'nl_NL' => 'hostinger.nl',
        'en_GB' => 'hostinger.co.uk',
        'el_GR' => 'hostinger.gr',
        'cs_CZ' => 'hostinger.cz',
        'hu_HU' => 'hostinger.hu',
        'sv_SE' => 'hostinger.se',
        'da_DK' => 'hostinger.dk',
        'fi_FI' => 'hostinger.fi',
        'sk_SK' => 'hostinger.sk',
        'no_NO' => 'hostinger.no',
        'hr_HR' => 'hostinger.hr',
        'zh_HK' => 'hostinger.com.hk',
        'he_IL' => 'hostinger.co.il',
        'lv_LV' => 'hostinger.lv',
        'et_EE' => 'hostinger.ee',
        'ur_PK' => 'hostinger.pk',
    );

    public function hosting_renewal_url( string $domain = '' ): string {
        return $this->build_login_url(
            array(
                'section'          => 'website-dashboard',
                'action'           => 'renew',
                'redirectLocation' => 'wordpress_admin',
            ),
            $domain
        );
    }

    public function resource_usage_url( string $domain = '' ): string {
        return $this->build_login_url(
            array(
                'section' => 'order_order_usage',
            ),
            $domain
        );
    }

    public function upgrade_url( string $domain = '' ): string {
        return $this->build_login_url(
            array(
                'section' => 'order_upgrade',
            ),
            $domain
        );
    }

    public function domain_renewal_url( string $domain = '' ): string {
        return $this->build_login_url(
            array(
                'section' => 'domain-management',
                'action'  => 'renew-modal',
            ),
            $domain
        );
    }

    public function connect_domain_url(): string {
        $site_url = preg_replace( '#^https?://#', '', (string) get_site_url() );

        return add_query_arg(
            array(
                'websiteType' => 'wordpress',
                'redirectUrl' => self::HPANEL_WEBSITES_URL,
            ),
            self::HPANEL_ADD_DOMAIN_URL . $site_url . '/select'
        );
    }

    public function build_login_url( array $params, string $domain = '' ): string {
        $domain = $domain !== '' ? $domain : $this->get_site_domain();

        return add_query_arg(
            array_merge( array( 'domain' => $domain ), $params ),
            'https://auth.' . $this->get_reseller_domain() . '/login'
        );
    }

    public function normalize_domain( string $domain ): string {
        return preg_replace( '/\s+/', '', $domain );
    }

    private function get_reseller_domain(): string {
        if ( isset( $_SERVER['H_STAGING'] ) && filter_var( $_SERVER['H_STAGING'], FILTER_VALIDATE_BOOLEAN ) === true ) {
            return 'hostinger.dev';
        }

        $reseller = get_option( 'hostinger_reseller', '' );
        if ( ! empty( $reseller ) ) {
            return $reseller;
        }

        return self::HOSTINGER_LOCALES[ get_locale() ] ?? 'hostinger.com';
    }

    private function get_site_domain(): string {
        $domain = wp_parse_url( get_site_url(), PHP_URL_HOST );

        return $domain ? $domain : '';
    }
}
