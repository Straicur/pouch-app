<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class AccessGrantResponseDTO
{
    public function __construct(
        private readonly string $resource,
        private readonly int $expires,
        private readonly string $signature,
    ) {}

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getExpires(): int
    {
        return $this->expires;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }
}
