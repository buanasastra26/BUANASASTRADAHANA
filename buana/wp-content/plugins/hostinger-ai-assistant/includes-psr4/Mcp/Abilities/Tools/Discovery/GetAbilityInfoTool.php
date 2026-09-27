<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Discovery;

use Hostinger\AiAssistant\Mcp\Abilities\AbilityCatalog;
use Hostinger\AiAssistant\Mcp\Abilities\AbilitiesRegistry;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class GetAbilityInfoTool {
    public const ABILITY_NAME = 'hostinger-ai/get-ability-info';

    public function register(): void {
        wp_register_ability(
            self::ABILITY_NAME,
            array(
                'label'               => __( 'Get Tool Info', 'hostinger-ai-assistant' ),
                'description'         => __( 'Returns the full definition of a single Hostinger AI tool, including its input schema, so it can be called correctly through execute-ability. Use discover-abilities to obtain valid tool names.', 'hostinger-ai-assistant' ),
                'category'            => AbilitiesRegistry::DISCOVERY_CATEGORY,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'name' => array(
                            'type'        => 'string',
                            'description' => __( 'The full name of the tool to inspect, as returned by discover-abilities.', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'name' ),
                ),
                'execute_callback'    => array( $this, 'get_info' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => false,
                        'type'   => 'tool',
                    ),
                    'annotations'  => array(
                        'title'           => 'Get Tool Info',
                        'readOnlyHint'    => true,
                        'destructiveHint' => false,
                        'idempotentHint'  => true,
                        'openWorldHint'   => false,
                    ),
                ),
            )
        );
    }

    public function get_info( array $input = array() ): array|WP_Error {
        $name    = is_string( $input['name'] ?? null ) ? $input['name'] : '';
        $ability = AbilityCatalog::get_tool( $name );

        if ( is_wp_error( $ability ) ) {
            return $ability;
        }

        $meta = $ability->get_meta();

        return array(
            'name'          => $ability->get_name(),
            'label'         => $ability->get_label(),
            'description'   => $ability->get_description(),
            'input_schema'  => $ability->get_input_schema(),
            'output_schema' => $ability->get_output_schema(),
            'annotations'   => $meta['annotations'] ?? array(),
        );
    }
}
