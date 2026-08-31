<?php

declare(strict_types = 1);

namespace App\Security;

interface ConfigServiceInterface
{
    public function getAccessTokenTimeToLive(): int;

    public function getRefreshTokenTimeToLive(): int;

    /**
     * Base URL (scheme + host, no trailing slash) for genuinely external
     * links — Part 9's public item links (PUBLIC_APP_URL) — as opposed to
     * URLs the frontend fetches from its own current origin.
     */
    public function getPublicBaseUrl(): string;
}
