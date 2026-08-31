<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

use App\Entity\Category;
use App\Entity\Item;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\Security\SignedUrlServiceInterface;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

use function is_array;
use function is_int;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class AccessKeyGuard implements AccessKeyGuardInterface
{
    /**
     * Grants travel as a JSON array — a request can need more than one at
     * once (a locked item inside a locked category). The app is stateless
     * (security.yaml), so this is the only place a client-submitted key
     * unlock is remembered between requests.
     */
    public const string GRANTS_HEADER = 'X-Pouch-Access-Grants';

    public function __construct(
        private AccessKeyServiceInterface $accessKeyService,
        private SignedUrlServiceInterface $signedUrlService,
    ) {}

    #[Override]
    public function assertCategoryUnlocked(Category $category, Request $request): void
    {
        if (false === $this->isCategoryUnlocked($category, $request)) {
            throw new ForbiddenException(message: 'category.locked');
        }
    }

    #[Override]
    public function assertItemUnlocked(Item $item, Request $request): void
    {
        if (false === $this->isItemUnlocked($item, $request)) {
            throw new ForbiddenException(message: 'item.locked');
        }
    }

    #[Override]
    public function isCategoryUnlocked(Category $category, Request $request): bool
    {
        $holder = $this->accessKeyService->findEffectiveKeyHolder($category);

        if (null === $holder) {
            return true;
        }

        return $this->hasValidGrant($request, AccessKeyResource::forCategory($holder->getId()));
    }

    #[Override]
    public function isItemUnlocked(Item $item, Request $request): bool
    {
        if (false === $this->isCategoryUnlocked($item->getCategory(), $request)) {
            return false;
        }

        if (null === $item->getAccessKeyHash()) {
            return true;
        }

        return $this->hasValidGrant($request, AccessKeyResource::forItem($item->getId()));
    }

    private function hasValidGrant(Request $request, string $resource): bool
    {
        foreach ($this->parseGrants($request) as $grant) {
            if ($grant->resource === $resource && $this->signedUrlService->isValid($grant->resource, $grant->expires, $grant->signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<AccessGrant>
     */
    private function parseGrants(Request $request): array
    {
        $header = $request->headers->get(self::GRANTS_HEADER);

        if (null === $header || '' === $header) {
            return [];
        }

        try {
            $decoded = json_decode($header, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        if (false === is_array($decoded)) {
            return [];
        }

        $grants = [];

        foreach ($decoded as $entry) {
            if (false === is_array($entry)) {
                continue;
            }

            if (false === isset($entry['resource'], $entry['expires'], $entry['signature'])) {
                continue;
            }

            if (false === is_string($entry['resource']) || false === is_int($entry['expires']) || false === is_string($entry['signature'])) {
                continue;
            }

            $grants[] = new AccessGrant(resource: $entry['resource'], expires: $entry['expires'], signature: $entry['signature']);
        }

        return $grants;
    }
}
