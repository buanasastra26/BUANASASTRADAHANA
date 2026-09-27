<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools\Discovery;

use Hostinger\AiAssistant\Mcp\Abilities\AbilityCatalog;
use Hostinger\AiAssistant\Mcp\Abilities\AbilitiesRegistry;
use stdClass;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class DiscoverAbilitiesTool {
    public const ABILITY_NAME = 'hostinger-ai/discover-abilities';

    public function register(): void {
        wp_register_ability(
            self::ABILITY_NAME,
            array(
                'label'               => __( 'Discover Tools', 'hostinger-ai-assistant' ),
                'description'         => __( 'Lists every Hostinger AI tool available on this site with its name, label and description. Call get-ability-info with a name from this list to read its input schema, then execute-ability to run it.', 'hostinger-ai-assistant' ),
                'category'            => AbilitiesRegistry::DISCOVERY_CATEGORY,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => new stdClass(),
                ),
                'output_schema'       => array(
                    'type'       => 'object',
                    'properties' => array(
                        'abilities' => array(
                            'type'  => 'array',
                            'items' => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'name'        => array( 'type' => 'string' ),
                                    'label'       => array( 'type' => 'string' ),
                                    'description' => array( 'type' => 'string' ),
                                ),
                            ),
                        ),
                    ),
                ),
                'execute_callback'    => array( $this, 'discover' ),
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
                        'title'           => 'Discover Tools',
                        'readOnlyHint'    => true,
                        'destructiveHint' => false,
                        'idempotentHint'  => true,
                        'openWorldHint'   => false,
                    ),
                ),
            )
        );
    }

    public function discover( array $input = array() ): array {
        $abilities = array();

        foreach ( AbilityCatalog::get_by_type( 'tool' ) as $ability ) {
            $abilities[] = array(
                'name'        => $ability->get_name(),
                'label'       => $ability->get_label(),
                'description' => $ability->get_description(),
            );
        }

        return array(
            'abilities' => $abilities,
        );
    }
}
