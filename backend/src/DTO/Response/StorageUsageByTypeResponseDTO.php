<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class StorageUsageByTypeResponseDTO
{
    public function __construct(
        private readonly string $type,
        private readonly int $totalBytes,
        private readonly int $itemCount,
    ) {}

    public function getType(): string
    {
        return $this->type;
    }

    public function getTotalBytes(): int
    {
        return $this->totalBytes;
    }

    public function getItemCount(): int
    {
        return $this->itemCount;
    }
}
