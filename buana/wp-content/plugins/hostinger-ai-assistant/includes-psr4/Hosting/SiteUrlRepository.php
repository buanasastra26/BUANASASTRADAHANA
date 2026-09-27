<?php

namespace Hostinger\AiAssistant\Hosting;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class SiteUrlRepository {
    private const FREE_SUBDOMAINS = array( 'hostingersite.com', 'hostingersite.dev' );

    public function get_host(): string {
        return (string) preg_replace( '#^https?://#', '', (string) get_option( 'siteurl' ) );
    }

    public function is_temporary_domain(): bool {
        $host = $this->get_host();

        if ( $host === '' ) {
            return false;
        }

        foreach ( self::FREE_SUBDOMAINS as $subdomain ) {
            if ( strpos( $host, $subdomain ) !== false ) {
                return true;
            }
        }

        return false;
    }

    public function is_custom_domain(): bool {
        return $this->get_host() !== '' && ! $this->is_temporary_domain();
    }
}
