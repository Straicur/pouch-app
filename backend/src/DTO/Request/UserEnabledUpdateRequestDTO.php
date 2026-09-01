<?php

declare(strict_types = 1);

namespace App\DTO\Request;

class UserEnabledUpdateRequestDTO
{
    public function __construct(
        private readonly bool $enabled,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
