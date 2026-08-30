<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class DownloadLinkResponseDTO
{
    public function __construct(
        private readonly string $url,
        private readonly string $expiresAt,
    ) {}

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getExpiresAt(): string
    {
        return $this->expiresAt;
    }
}
