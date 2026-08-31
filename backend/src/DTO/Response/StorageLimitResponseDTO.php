<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class StorageLimitResponseDTO
{
    public function __construct(
        private readonly string $type,
        private readonly int $maxSizeBytes,
    ) {}

    public function getType(): string
    {
        return $this->type;
    }

    public function getMaxSizeBytes(): int
    {
        return $this->maxSizeBytes;
    }
}
