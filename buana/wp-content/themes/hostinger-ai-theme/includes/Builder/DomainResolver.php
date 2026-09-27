<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\WpHelper\Utils as Helper;

defined( 'ABSPATH' ) || exit;

class DomainResolver {
    private Helper $helper;

    public function __construct( Helper $helper ) {
        $this->helper = $helper;
    }

    private const PREVIEW_HOST_SUFFIX = '.hostingersite.com';

    public function get_current_domain(): string {
        if ( defined( 'HOSTINGER_DOMAIN_OVERRIDE' ) && ! empty( HOSTINGER_DOMAIN_OVERRIDE ) ) {
            return (string) HOSTINGER_DOMAIN_OVERRIDE;
        }

        $host = preg_replace( '/^www\./', '', (string) $this->helper->getHostInfo() );

        if ( $this->is_preview_host( $host ) ) {
            $site_url = $this->get_site_url_from_db();
            if ( $site_url !== '' ) {
                $real_host = preg_replace( '/^www\./', '', $site_url );
                if ( $real_host !== '' && $real_host !== $host ) {
                    return $real_host;
                }
            }
        }

        return $host;
    }

    private function get_site_url_from_db(): string {
        if ( method_exists( $this->helper, 'getSiteUrlFromDb' ) ) {
            $value = (string) $this->helper->getSiteUrlFromDb();
            if ( $value !== '' ) {
                return $value;
            }
        }

        $site_url = (string) get_option( 'siteurl', '' );

        return (string) preg_replace( '#^https?://#', '', $site_url );
    }

    private function is_preview_host( string $host ): bool {
        if ( method_exists( $this->helper, 'isPreviewDomain' ) && $this->helper->isPreviewDomain() ) {
            return true;
        }

        $suffix = self::PREVIEW_HOST_SUFFIX;

        return $host !== '' && substr( $host, -strlen( $suffix ) ) === $suffix;
    }
}
