<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class PublicItemLinkResponseDTO
{
    public function __construct(
        private readonly string $viewUrl,
        private readonly ?string $downloadUrl,
        private readonly ?string $thumbnailUrl,
        private readonly string $expiresAt,
    ) {}

    public function getViewUrl(): string
    {
        return $this->viewUrl;
    }

    public function getDownloadUrl(): ?string
    {
        return $this->downloadUrl;
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }

    public function getExpiresAt(): string
    {
        return $this->expiresAt;
    }
}
