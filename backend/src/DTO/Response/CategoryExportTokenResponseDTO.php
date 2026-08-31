<?php

declare(strict_types = 1);

namespace App\DTO\Response;

/**
 * Post-review fix: replaces relaying access-key grants as a raw "?grants="
 * query parameter on the export/backup download link — see
 * CategoryController's export()/exportToken() for the full reasoning. This
 * is what POST .../export-token hands back: an opaque, short-lived (60s —
 * see CategoryController::EXPORT_TOKEN_TTL_SECONDS) token, not the grants
 * themselves, so nothing sensitive ever rides in a URL, browser history, or
 * a proxy access log.
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
