<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class GetKitById extends BaseElementorTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-get-kit-by-id',
            array(
                'label'               => __( 'Get Elementor Kit by ID', 'hostinger-ai-assistant' ),
                'description'         => __( 'Retrieves a specific Elementor kit by post ID with all settings including global colors, typography presets, and theme style settings.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'kit_id'             => array(
                            'type'        => 'integer',
                            'description' => __( 'The post ID of the Elementor kit', 'hostinger-ai-assistant' ),
                        ),
                        'include_colors'     => array(
                            'type'        => 'boolean',
                            'description' => __( 'Include global colors in response. Default: true', 'hostinger-ai-assistant' ),
                            'default'     => true,
                        ),
                        'include_typography' => array(
                            'type'        => 'boolean',
                            'description' => __( 'Include global typography in response. Default: true', 'hostinger-ai-assistant' ),
                            'default'     => true,
                        ),
                        'include_settings'   => array(
                            'type'        => 'boolean',
                            'description' => __( 'Include all other kit settings. Default: false', 'hostinger-ai-assistant' ),
                            'default'     => false,
                        ),
                    ),
                    'required'   => array( 'kit_id' ),
                ),
                'execute_callback'    => array( $this, 'execute' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => $this->type,
                    ),
                    'annotations'  => array(
                        'title'         => 'Get Kit By ID',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    public function execute( array $input ): array {
        $kit_id             = $input['kit_id'];
        $include_colors     = $input['include_colors'] ?? true;
        $include_typography = $input['include_typography'] ?? true;
        $include_settings   = $input['include_settings'] ?? false;

        return $this->get_kit_data( $kit_id, $include_colors, $include_typography, $include_settings );
    }
}
