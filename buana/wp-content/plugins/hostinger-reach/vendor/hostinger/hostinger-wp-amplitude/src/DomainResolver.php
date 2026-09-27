<?php

namespace Hostinger\Amplitude;

use Hostinger\WpHelper\Utils as Helper;

class DomainResolver
{
    private const PREVIEW_HOST_SUFFIX = '.hostingersite.com';

    private Helper $helper;

    public function __construct(Helper $helper)
    {
        $this->helper = $helper;
    }

    public function getCurrentDomain(): string
    {
        if (defined('HOSTINGER_DOMAIN_OVERRIDE') && ! empty(HOSTINGER_DOMAIN_OVERRIDE)) {
            return (string) HOSTINGER_DOMAIN_OVERRIDE;
        }

        $host = preg_replace('/^www\./', '', (string) $this->helper->getHostInfo());

        if ($this->isPreviewHost($host)) {
            $siteUrl = $this->getSiteUrlFromDb();

            if ($siteUrl !== '') {
                $realHost = preg_replace('/^www\./', '', $siteUrl);

                if ($realHost !== '' && $realHost !== $host) {
                    return $realHost;
                }
            }
        }

        return $host;
    }

    private function getSiteUrlFromDb(): string
    {
        if (method_exists($this->helper, 'getSiteUrlFromDb')) {
            $value = (string) $this->helper->getSiteUrlFromDb();

            if ($value !== '') {
                return $value;
            }
        }

        $siteUrl = (string) get_option('siteurl', '');

        return (string) preg_replace('#^https?://#', '', $siteUrl);
    }

    private function isPreviewHost(string $host): bool
    {
        if (method_exists($this->helper, 'isPreviewDomain') && $this->helper->isPreviewDomain()) {
            return true;
        }

        $suffix = self::PREVIEW_HOST_SUFFIX;

        return $host !== '' && substr($host, -strlen($suffix)) === $suffix;
    }
}
