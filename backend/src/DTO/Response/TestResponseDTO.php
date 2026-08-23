<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class TestResponseDTO
{
    public function __construct(
        private readonly string $email,
    ) {}

    public function getEmail(): string
    {
        return $this->email;
    }
}
