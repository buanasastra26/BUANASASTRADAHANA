<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Discovery;

use Hostinger\AiAssistant\Mcp\Abilities\AbilityCatalog;
use Hostinger\AiAssistant\Mcp\Abilities\AbilitiesRegistry;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ExecuteAbilityTool {
    public const ABILITY_NAME = 'hostinger-ai/execute-ability';

    public function register(): void {
        wp_register_ability(
            self::ABILITY_NAME,
            array(
                'label'               => __( 'Execute Tool', 'hostinger-ai-assistant' ),
                'description'         => __( 'Runs a Hostinger AI tool by name with the given parameters. Read the tool input schema with get-ability-info first, since parameters are validated against it. The target tool enforces its own permissions.', 'hostinger-ai-assistant' ),
                'category'            => AbilitiesRegistry::DISCOVERY_CATEGORY,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'name'       => array(
                            'type'        => 'string',
                            'description' => __( 'The full name of the tool to run, as returned by discover-abilities.', 'hostinger-ai-assistant' ),
                        ),
                        'parameters' => array(
                            'type'        => 'object',
                            'description' => __( 'Parameters for the target tool, matching the input schema returned by get-ability-info. Omit for tools that take no parameters.', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'name' ),
                ),
                'execute_callback'    => array( $this, 'execute_ability' ),
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
                        'title'           => 'Execute Tool',
                        'readOnlyHint'    => false,
                        'destructiveHint' => true,
                        'idempotentHint'  => false,
                        'openWorldHint'   => false,
                    ),
                ),
            )
        );
    }

    public function execute_ability( array $input = array() ): array|WP_Error {
        $name    = is_string( $input['name'] ?? null ) ? $input['name'] : '';
        $ability = AbilityCatalog::get_tool( $name );

        if ( is_wp_error( $ability ) ) {
            return $ability;
        }

        $parameters = is_array( $input['parameters'] ?? null ) ? $input['parameters'] : array();
        $has_schema = ! empty( $ability->get_input_schema() );

        if ( ! $has_schema && ! empty( $parameters ) ) {
            return new WP_Error(
                'ability_takes_no_parameters',
                sprintf(
                    /* translators: %s: target tool name */
                    __( 'The tool "%s" does not accept parameters.', 'hostinger-ai-assistant' ),
                    $name
                ),
                array( 'status' => 400 )
            );
        }

        $result = $ability->execute( $has_schema ? $parameters : null );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array(
            'ability' => $name,
            'result'  => $result,
        );
    }
}
