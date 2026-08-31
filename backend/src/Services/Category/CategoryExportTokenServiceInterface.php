<?php

declare(strict_types = 1);

namespace App\Services\Category;

use App\DTO\Response\CategoryExportTokenResponseDTO;
use Symfony\Component\HttpFoundation\Request;

/**
 * A plain navigation to GET .../export (used so the ZIP streams instead of
 * being buffered as a Blob — see frontend's triggerDownload.ts) can't carry
 * the X-Pouch-Access-Grants header. This mints/verifies a short-lived,
 * signed token that carries the grants instead, so nothing sensitive ends up
 * in the URL/browser history/proxy logs.
 */
interface CategoryExportTokenServiceInterface
{
    /**
     * @return CategoryExportTokenResponseDTO a token valid for TOKEN_TTL_SECONDS
     */
    public function issue(int $categoryId, int $userId, Request $request): CategoryExportTokenResponseDTO;

    /**
     * Reads "?token=" off $request and, if it's present and valid for
     * $categoryId/$userId, sets the grants it carries as the request's own
     * X-Pouch-Access-Grants header. A no-op (not an error) when the token is
     * missing, expired, tampered with, or was minted for a different
     * category/user — same leniency the endpoint already has for a
     * missing/invalid grant.
     */
    public function apply(Request $request, int $categoryId, int $userId): void;
}
