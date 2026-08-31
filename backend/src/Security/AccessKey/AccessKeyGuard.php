<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\User;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\Repository\CategoryRepository;
use App\Security\SignedUrlServiceInterface;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use WeakMap;

use function is_array;
use function is_int;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class AccessKeyGuard implements AccessKeyGuardInterface
{
    // GRANTS_HEADER now lives on AccessKeyGuardInterface (inherited here) — see its own doc comment.

    /**
     * Post-review fix: parseGrants() used to re-decode the same request's
     * grants header from scratch on every single hasValidGrant() call —
     * cheap for one item, wasteful when lockedCategoryIds() calls it once
     * per category on every GET /api/items. Keyed by Request (a WeakMap, not
     * a plain array, so it
     * never outlives — or needs manual clearing between — the request
     * itself, which matters if this service is ever reused across requests
     * in a long-running worker). Readonly property, mutable object: `readonly`
     * only stops *this* property from being reassigned, not the WeakMap
     * instance it points to from being written to — the standard pattern for
     * a cache on an otherwise-immutable, request-scoped-by-convention service.
     *
     * @var WeakMap<Request, list<AccessGrant>>
     */
    private WeakMap $grantsCache;

    public function __construct(
        private AccessKeyServiceInterface $accessKeyService,
        private SignedUrlServiceInterface $signedUrlService,
        private Security $security,
        private CategoryRepository $categoryRepository,
    ) {
        $this->grantsCache = new WeakMap();
    }

    /**
     * Post-review fix: a grant only matches for the user it was issued to —
     * see AccessKeyResource's doc comment. Returns null (never matches any
     * grant) rather than throwing: an unauthenticated request should simply
     * see the resource as locked, not 500.
     */
    private function currentUserId(): ?int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->getId() : null;
    }

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
        // Checked (and reported) separately from the item's own key below —
        // isItemUnlocked() alone can't tell the caller *which* of the two
        // independent locks (Part 7) is the one actually blocking them.
        $this->assertCategoryUnlocked($item->getCategory(), $request);

        if (null !== $item->getAccessKeyHash() && false === $this->hasValidItemGrant($item, $request)) {
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

        $userId = $this->currentUserId();

        if (null === $userId) {
            return false;
        }

        return $this->hasValidGrant($request, AccessKeyResource::forCategory($holder->getId(), $holder->getAccessKeyVersion(), $userId));
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

        return $this->hasValidItemGrant($item, $request);
    }

    #[Override]
    public function isItemOwnKeyUnlocked(Item $item, Request $request): bool
    {
        if (null === $item->getAccessKeyHash()) {
            return true;
        }

        return $this->hasValidItemGrant($item, $request);
    }

    #[Override]
    public function lockedCategoryIds(Request $request): array
    {
        // Post-review fix: scalar rows (id/parentId/accessKeyHash/
        // accessKeyVersion), not full Category entities — see
        // CategoryRepository::findAllForLockCheck()'s own doc comment.
        $rows = $this->categoryRepository->findAllForLockCheck();

        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['id']] = $row;
        }

        $userId = $this->currentUserId();

        // Memoized per holder — every category sharing an inherited key
        // resolves to the same holder, so its grant only needs checking once
        // no matter how many categories are in its subtree.
        $holderIsLocked = [];
        $locked = [];

        foreach ($rows as $row) {
            $holder = $this->effectiveKeyHolderRow($row, $byId);
            if (null === $holder) {
                continue;
            }

            $holderId = $holder['id'];
            if (false === isset($holderIsLocked[$holderId])) {
                $holderIsLocked[$holderId] = null === $userId
                    || false === $this->hasValidGrant($request, AccessKeyResource::forCategory($holderId, $holder['accessKeyVersion'], $userId));
            }

            if ($holderIsLocked[$holderId]) {
                $locked[] = $row['id'];
            }
        }

        return $locked;
    }

    /**
     * Scalar-row counterpart of AccessKeyService::findEffectiveKeyHolder() —
     * same "walk up until a key is found" logic, over the id-keyed map
     * lockedCategoryIds() builds instead of live Category::getParent() calls.
     *
     * @param array{id: int, parentId: int|null, accessKeyHash: string|null, accessKeyVersion: int}             $row
     * @param array<int, array{id: int, parentId: int|null, accessKeyHash: string|null, accessKeyVersion: int}> $byId
     *
     * @return array{id: int, parentId: int|null, accessKeyHash: string|null, accessKeyVersion: int}|null
     */
    private function effectiveKeyHolderRow(array $row, array $byId): ?array
    {
        $current = $row;

        while (null !== $current) {
            if (null !== $current['accessKeyHash']) {
                return $current;
            }

            $current = null !== $current['parentId'] ? ($byId[$current['parentId']] ?? null) : null;
        }

        return null;
    }

    private function hasValidItemGrant(Item $item, Request $request): bool
    {
        $userId = $this->currentUserId();

        if (null === $userId) {
            return false;
        }

        return $this->hasValidGrant($request, AccessKeyResource::forItem($item->getId(), $item->getAccessKeyVersion(), $userId));
    }

    private function hasValidGrant(Request $request, string $resource): bool
    {
        return array_any($this->parseGrants($request), fn ($grant): bool => $grant->resource === $resource && $this->signedUrlService->isValid($grant->resource, $grant->expires, $grant->signature));
    }

    /**
     * @return list<AccessGrant>
     */
    private function parseGrants(Request $request): array
    {
        if (isset($this->grantsCache[$request])) {
            return $this->grantsCache[$request];
        }

        $grants = $this->doParseGrants($request);
        $this->grantsCache[$request] = $grants;

        return $grants;
    }

    /**
     * @return list<AccessGrant>
     */
    private function doParseGrants(Request $request): array
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

            if (false === is_string($entry['resource'])) {
                continue;
            }

            if (false === is_int($entry['expires'])) {
                continue;
            }

            if (false === is_string($entry['signature'])) {
                continue;
            }

            $grants[] = new AccessGrant(resource: $entry['resource'], expires: $entry['expires'], signature: $entry['signature']);
        }

        return $grants;
    }
}
