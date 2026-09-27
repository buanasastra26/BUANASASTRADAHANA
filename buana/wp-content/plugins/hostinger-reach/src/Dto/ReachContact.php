<?php

namespace Hostinger\Reach\Dto;

class ReachContact {
    private string $email;
    private string $name;
    private string $surname;
    private array $metadata;

    public function __construct( string $email, string $name, string $surname, array $metadata = array() ) {
        $this->email    = $email;
        $this->name     = $name;
        $this->surname  = $surname;
        $this->metadata = $metadata;
    }

    public function to_array(): array {
        return array(
            'email'    => $this->email,
            'name'     => $this->name,
            'surname'  => $this->surname,
            'metadata' => $this->metadata,
        );
    }
}
