<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Utils;

defined( 'ABSPATH' ) || exit;

trait SoftwareIdTrait {

    private function get_software_id(): ?string {
        if ( defined( 'HOSTINGER_SOFTWARE_ID_OVERRIDE' ) && ! empty( HOSTINGER_SOFTWARE_ID_OVERRIDE ) ) {
            return (string) HOSTINGER_SOFTWARE_ID_OVERRIDE;
        }

        $software_id = get_option( 'hostinger_sfid' );
        if ( ! empty( $software_id ) ) {
            return (string) $software_id;
        }

        $config_software_id = ( new Config() )->getConfigValue( 'software_id', '' );
        if ( ! empty( $config_software_id ) ) {
            update_option( 'hostinger_sfid', $config_software_id, true );

            return (string) $config_software_id;
        }

        $domain = ( new DomainResolver( new Utils() ) )->get_current_domain();
        if ( empty( $domain ) ) {
            return null;
        }

        $response = $this->wh_api_client->get( '/api/v1/installations', array( 'domain' => $domain ) );
        if ( empty( $response[0]['id'] ) ) {
            return null;
        }

        $software_id = (string) $response[0]['id'];

        update_option( 'hostinger_sfid', $software_id, true );

        return $software_id;
    }
}
