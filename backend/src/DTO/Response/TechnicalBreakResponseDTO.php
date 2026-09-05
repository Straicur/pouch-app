<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class TechnicalBreakResponseDTO
{
    public function __construct(
        private readonly bool $active,
        private readonly ?string $message,
        // null when !active — no break has been running since.
        private readonly ?string $since,
    ) {}

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getSince(): ?string
    {
        return $this->since;
    }
}
