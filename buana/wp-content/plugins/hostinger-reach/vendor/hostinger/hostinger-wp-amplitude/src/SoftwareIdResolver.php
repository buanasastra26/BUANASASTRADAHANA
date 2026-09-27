<?php

namespace Hostinger\Amplitude;

use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils as Helper;

class SoftwareIdResolver
{
    public const SOFTWARE_ID_OPTION = 'hostinger_sfid';
    public const INSTALLATIONS_ENDPOINT = '/api/v1/installations';
    public const LOOKUP_FAILED_TRANSIENT = 'amplitude_sfid_lookup_failed';
    public const LOOKUP_FAILED_TTL = 3600;

    private Helper $helper;
    private Config $configHandler;
    private Client $client;
    private DomainResolver $domainResolver;

    public function __construct(
        Helper $helper,
        Config $configHandler,
        Client $client,
        ?DomainResolver $domainResolver = null
    ) {
        $this->helper         = $helper;
        $this->configHandler  = $configHandler;
        $this->client         = $client;
        $this->domainResolver = $domainResolver ?? new DomainResolver($helper);
    }

    public function getSoftwareId(): ?string
    {
        if (defined('HOSTINGER_SOFTWARE_ID_OVERRIDE') && ! empty(HOSTINGER_SOFTWARE_ID_OVERRIDE)) {
            return (string) HOSTINGER_SOFTWARE_ID_OVERRIDE;
        }

        $softwareId = get_option(self::SOFTWARE_ID_OPTION);

        if (! empty($softwareId)) {
            return (string) $softwareId;
        }

        $configSoftwareId = $this->configHandler->getConfigValue('software_id', '');

        if (! empty($configSoftwareId)) {
            update_option(self::SOFTWARE_ID_OPTION, $configSoftwareId, true);

            return (string) $configSoftwareId;
        }

        return $this->lookupSoftwareId();
    }

    private function lookupSoftwareId(): ?string
    {
        if (get_transient(self::LOOKUP_FAILED_TRANSIENT)) {
            return null;
        }

        $domain = $this->domainResolver->getCurrentDomain();

        if (empty($domain)) {
            return null;
        }

        $installations = $this->decodeInstallations(
            $this->client->get(self::INSTALLATIONS_ENDPOINT, [ 'domain' => $domain ])
        );

        if (empty($installations[0]['id'])) {
            set_transient(self::LOOKUP_FAILED_TRANSIENT, true, self::LOOKUP_FAILED_TTL);

            return null;
        }

        $softwareId = (string) $installations[0]['id'];

        update_option(self::SOFTWARE_ID_OPTION, $softwareId, true);

        return $softwareId;
    }

    private function decodeInstallations($response): array
    {
        if (is_wp_error($response)) {
            return [];
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        return is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    }
}
