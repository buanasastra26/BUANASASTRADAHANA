<?php

namespace Hostinger\AiAssistant\Mcp\Dto;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class RestOperationConfig {
    private string $operation;
    private string $tool_name;
    private string $label;
    private string $description;
    private string $controller_class;
    private string $route;
    private string $resource_type;
    private $permission_callback;

    private array $input_schema_modifications;
    private array $output_schema_modifications;
    private array $meta_modifications;
    private bool $skip_ids;
    private array $method_map = array(
        'list'   => 'GET',
        'get'    => 'GET',
        'create' => 'POST',
        'update' => 'PUT',
        'delete' => 'DELETE',
        'report' => 'GET',
        'batch'  => 'POST',
    );

    public function __construct( string $operation, string $tool_name, string $label, string $description, string $controller_class, string $route, string $resource_type = 'post', ?callable $permission_callback = null, array $input_schema_modifications = array(), array $output_schema_modifications = array(), array $meta_modifications = array(), bool $skip_ids = false ) {
        $this->operation                   = $operation;
        $this->tool_name                   = $tool_name;
        $this->label                       = $label;
        $this->description                 = $description;
        $this->controller_class            = $controller_class;
        $this->route                       = $route;
        $this->resource_type               = $resource_type;
        $this->permission_callback         = $permission_callback;
        $this->input_schema_modifications  = $input_schema_modifications;
        $this->output_schema_modifications = $output_schema_modifications;
        $this->meta_modifications          = $meta_modifications;
        $this->skip_ids                    = $skip_ids;
    }

    public static function from_array( string $operation, array $config, string $controller_class, string $route, string $resource_type = 'post' ): self {
        return new self(
            $operation,
            $config['tool_name'] ?? '',
            $config['label'] ?? '',
            $config['description'] ?? '',
            $controller_class,
            $route,
            $resource_type,
            $config['permission_callback'] ?? null,
            $config['input_schema_modifications'] ?? array(),
            $config['output_schema_modifications'] ?? array(),
            $config['meta'] ?? array(),
            $config['skip_ids'] ?? false
        );
    }

    public function is_valid(): bool {
        return ! empty( $this->tool_name ) && ! empty( $this->label ) && ! empty( $this->description );
    }

    public function get_operation(): string {
        return $this->operation;
    }

    public function get_tool_name(): string {
        return $this->tool_name;
    }

    public function get_label(): string {
        return $this->label;
    }

    public function get_description(): string {
        return $this->description;
    }

    public function get_controller_class(): string {
        return $this->controller_class;
    }

    public function get_route(): string {
        return $this->route;
    }

    public function get_resource_type(): string {
        return $this->resource_type;
    }

    public function get_permission_callback(): ?callable {
        return $this->permission_callback;
    }

    public function get_input_schema_modifications(): array {
        return $this->input_schema_modifications;
    }

    public function has_input_schema_modifications(): bool {
        return ! empty( $this->input_schema_modifications );
    }

    public function get_output_schema_modifications(): array {
        return $this->output_schema_modifications;
    }

    public function has_output_schema_modifications(): bool {
        return ! empty( $this->output_schema_modifications );
    }

    public function get_meta_modifications(): array {
        return $this->meta_modifications;
    }

    public function has_meta_modifications(): bool {
        return ! empty( $this->meta_modifications );
    }

    public function get_http_method_for_operation(): string {
        return $this->method_map[ $this->get_operation() ] ?? 'GET';
    }

    public function should_skip_ids(): bool {
        return $this->skip_ids;
    }
}
