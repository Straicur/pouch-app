<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class ItemVersionResponseDTO
{
    public function __construct(
        private readonly int $version,
        private readonly string $originalFilename,
        private readonly string $mimeType,
        private readonly int $size,
        private readonly string $createdAt,
    ) {}

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
