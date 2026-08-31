<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\User;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Security\SignedUrlServiceInterface;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

use function is_array;
use function is_int;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class AccessKeyGuard implements AccessKeyGuardInterface
{
    // GRANTS_HEADER now lives on AccessKeyGuardInterface (inherited here) — see its own doc comment.

    public function __construct(
        private AccessKeyServiceInterface $accessKeyService,
        private SignedUrlServiceInterface $signedUrlService,
        private Security $security,
        private CategoryRepository $categoryRepository,
        private ItemRepository $itemRepository,
    ) {}

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
        $categories = $this->categoryRepository->findAllOrderedByName();
        $userId = $this->currentUserId();

        // Memoized per holder — every category sharing an inherited key
        // resolves to the same holder, so its grant only needs checking once
        // no matter how many categories are in its subtree.
        $holderIsLocked = [];
        $locked = [];

        foreach ($categories as $category) {
            $holder = $this->accessKeyService->findEffectiveKeyHolder($category);
            if (null === $holder) {
                continue;
            }

            $holderId = $holder->getId();
            if (false === isset($holderIsLocked[$holderId])) {
                $holderIsLocked[$holderId] = null === $userId
                    || false === $this->hasValidGrant($request, AccessKeyResource::forCategory($holderId, $holder->getAccessKeyVersion(), $userId));
            }

            if ($holderIsLocked[$holderId]) {
                $locked[] = $category->getId();
            }
        }

        return $locked;
    }

    #[Override]
    public function lockedItemIdsWithOwnKey(Request $request): array
    {
        $userId = $this->currentUserId();
        $locked = [];

        foreach ($this->itemRepository->findAllWithOwnAccessKey() as $item) {
            $isUnlocked = null !== $userId
                && $this->hasValidGrant($request, AccessKeyResource::forItem($item->getId(), $item->getAccessKeyVersion(), $userId));

            if (false === $isUnlocked) {
                $locked[] = $item->getId();
            }
        }

        return $locked;
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
