<?php

namespace Hostinger\Reach\Api;

use Hostinger\Reach\Setup\Encrypt;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ResourceIdManager {
    public const TRANSIENT_NAME            = 'hostinger_reach_resource_id';
    public const TRANSIENT_EXPIRATION_TIME = HOUR_IN_SECONDS;
    public const NON_EXISTENT_RESOURCE_ID  = 'non_existent_resource_id';

    public function store_resource_id( string $resource_id ): bool {
        return set_transient( self::TRANSIENT_NAME, $this->encrypt_resource_id( $resource_id ), self::TRANSIENT_EXPIRATION_TIME );
    }

    public function clear_resource_id(): string {
        return delete_transient( self::TRANSIENT_NAME );
    }

    public function get_resource_id(): string {
        return $this->decrypt_resource_id( get_transient( self::TRANSIENT_NAME, '' ) );
    }

    public function encrypt_resource_id( string $resource_id ): string {
        return Encrypt::encrypt( $resource_id );
    }

    public function decrypt_resource_id( string $resource_id ): string {
        return Encrypt::decrypt( $resource_id );
    }
}
