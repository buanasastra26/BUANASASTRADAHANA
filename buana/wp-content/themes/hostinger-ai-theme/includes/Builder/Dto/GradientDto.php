<?php

namespace Hostinger\AiTheme\Builder\Dto;

class GradientDto {
    public string $id;
    public string $gradient;

    public function __construct( string $id, string $gradient ) {
        $this->id = $id;
        $this->gradient = $gradient;
    }

    public function get_id(): string {
        return $this->id;
    }

    public function set_id( string $id ): void {
        $this->id = $id;
    }

    public function get_gradient(): string {
        return $this->gradient;
    }

    public function set_gradients( array $gradient ): void {
        $this->gradient = $gradient;
    }

    public function get_main_color(): string {
        return explode(',', $this->get_gradient())[0];
    }

    public function to_array(): array {
        return [
            'id' => $this->id,
            'gradient' => $this->gradient,
        ];
    }
}
