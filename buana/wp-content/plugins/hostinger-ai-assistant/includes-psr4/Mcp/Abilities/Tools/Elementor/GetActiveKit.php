<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class GetActiveKit extends BaseElementorTool {
    public function register(): void {
        wp_register_ability(
            'hostinger-ai/elementor-get-active-kit',
            array(
                'label'               => __( 'Get Elementor Active Kit', 'hostinger-ai-assistant' ),
                'description'         => __( 'Retrieves the active Elementor kit with all settings including global colors, typography presets, custom CSS, and theme style settings.', 'hostinger-ai-assistant' ),
                'category'            => $this->category,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
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
                        'title'         => 'Get Active Kit',
                        'readOnlyHint'  => true,
                        'openWorldHint' => false,
                    ),
                ),
            )
        );
    }

    public function execute( array $input ): array {
        $include_colors     = $input['include_colors'] ?? true;
        $include_typography = $input['include_typography'] ?? true;
        $include_settings   = $input['include_settings'] ?? false;

        $active_kit_id = get_option( 'elementor_active_kit' );
        if ( ! $active_kit_id ) {
            return array(
                'success'    => false,
                'error_code' => 'NO_ACTIVE_KIT',
                'message'    => __( 'No active Elementor kit found', 'hostinger-ai-assistant' ),
            );
        }

        return $this->get_kit_data( $active_kit_id, $include_colors, $include_typography, $include_settings );
    }
}
