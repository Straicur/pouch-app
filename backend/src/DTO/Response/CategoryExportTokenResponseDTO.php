<?php

declare(strict_types = 1);

namespace App\DTO\Response;

/**
 * What POST .../export-token hands back — see CategoryExportTokenService.
 */
class CategoryExportTokenResponseDTO
{
    public function __construct(
        private readonly string $token,
        private readonly string $expiresAt,
    ) {}

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresAt(): string
    {
        return $this->expiresAt;
    }
}
