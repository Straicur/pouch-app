<?php

declare(strict_types = 1);

namespace App\Services\Category;

use App\DTO\Response\CategoryExportTokenResponseDTO;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use Symfony\Component\HttpFoundation\Request;

/**
 * A plain navigation to GET .../export (used so the ZIP streams instead of
 * being buffered as a Blob — see frontend's triggerDownload.ts) can't carry
 * the X-Pouch-Access-Grants header. This mints/verifies a short-lived,
 * single-use, opaque token authorizing that one request instead, so nothing
 * sensitive ends up in the URL/browser history/proxy logs.
 */
interface CategoryExportTokenServiceInterface
{
    /**
     * @return CategoryExportTokenResponseDTO a token valid for TOKEN_TTL_SECONDS
     */
    public function issue(int $categoryId, int $userId, Request $request): CategoryExportTokenResponseDTO;

    /**
     * Reads "?token=" off $request and, if present, sets the grants it
     * carries as the request's own X-Pouch-Access-Grants header. A no-op
     * when no token was given at all — export() without one is legitimate
     * (see its own docs: "everything this account can see *without* a
     * key"). A token that *was* given but is missing, expired, already
     * used, or was minted for a different category/user is rejected loudly
     * instead — a caller that explicitly passed a token expects it to have
     * been honored, not silently downgraded to an unlocked-only export.
     *
     * @throws ForbiddenException if a token was given but isn't valid for $categoryId/$userId
     */
    public function apply(Request $request, int $categoryId, int $userId): void;
}
