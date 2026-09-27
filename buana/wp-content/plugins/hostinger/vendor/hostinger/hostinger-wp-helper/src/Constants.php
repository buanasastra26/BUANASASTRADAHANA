<?php

namespace Hostinger\WpHelper;

defined('ABSPATH') || exit;

class Constants
{
    public const TOKEN_HEADER  = 'X-Hpanel-Order-Token';
    public const DOMAIN_HEADER = 'X-Hpanel-Domain';
    public const HOSTINGER_REST_URI = 'https://rest-hosting.hostinger.com';
    public const CONFIG_PATH = ABSPATH . '.private/config.json';
    public const SITE_URL_OVERRIDE_CONST  = 'HOSTINGER_SITE_URL_OVERRIDE';
    public const API_TOKEN_OVERRIDE_CONST = 'HOSTINGER_API_TOKEN_OVERRIDE';
}
