<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Constants\ApiRoutes;
use Exception;

defined( 'ABSPATH' ) || exit;

class WebsiteTypeDetector {
    use SoftwareIdTrait;

    private RequestClient $wh_api_client;

    public function __construct( RequestClient $wh_api_client ) {
        $this->wh_api_client = $wh_api_client;
    }

    /**
     * @param string $description
     * @param string $brand_name
     *
     * @return array Sanitized website type strings, or empty array when none detected.
     * @throws Exception When the software id is unavailable.
     */
    public function detect( string $description, string $brand_name ): array {
        $software_id = $this->get_software_id();
        if ( empty( $software_id ) ) {
            throw new Exception( 'Software ID not available' );
        }

        $response_data = $this->wh_api_client->post(
            ApiRoutes::INSTALLATIONS_BASE . $software_id . '/content/detect/website-type',
            array(
                'description' => $description,
                'brandName'   => $brand_name,
            )
        );

        if ( empty( $response_data['websiteTypes'] ) ) {
            return array();
        }

        $types = is_array( $response_data['websiteTypes'] )
            ? $response_data['websiteTypes']
            : array( $response_data['websiteTypes'] );

        return array_map( static fn( $t ) => sanitize_text_field( (string) $t ), $types );
    }
}
