<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\User;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Security\Limiter\AccessKeyRateLimiterInterface;
use App\Security\SignedUrlServiceInterface;
use App\Services\Category\CategoryServiceInterface;
use App\Services\Item\ItemServiceInterface;
use LogicException;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final readonly class AccessKeyService implements AccessKeyServiceInterface
{
    /**
     * How long a grant stays valid after a correct unlock — deliberately much
     * longer than the 900s signed download links (Part 3): re-typing the key
     * every 15 minutes while browsing a protected category would defeat the
     * point of unlocking it.
     */
    private const int GRANT_TTL_SECONDS = 86_400;

    public function __construct(
        private CategoryServiceInterface $categoryService,
        private ItemServiceInterface $itemService,
        private CategoryRepository $categoryRepository,
        private ItemRepository $itemRepository,
        private AccessKeyHasherInterface $accessKeyHasher,
        private SignedUrlServiceInterface $signedUrlService,
        private AccessKeyRateLimiterInterface $accessKeyRateLimiter,
        private Security $security,
    ) {}

    /**
     * Post-review fix: a grant is bound to whoever's authenticated at the
     * moment it's issued — see AccessKeyResource's doc comment for why.
     */
    private function currentUserId(): int
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new LogicException('Access key unlock attempted without an authenticated user.');
        }

        return $user->getId();
    }

    #[Override]
    public function findEffectiveKeyHolder(Category $category): ?Category
    {
        $current = $category;

        while (null !== $current) {
            if (null !== $current->getAccessKeyHash()) {
                return $current;
            }

            $current = $current->getParent();
        }

        return null;
    }

    #[Override]
    public function setCategoryKey(int $categoryId, ?string $key): Category
    {
        $category = $this->categoryService->getById($categoryId);
        $category->setAccessKeyHash(null !== $key ? $this->accessKeyHasher->hash($key) : null);

        $this->categoryRepository->save($category);

        return $category;
    }

    #[Override]
    public function setItemKey(int $itemId, ?string $key): Item
    {
        $item = $this->itemService->getById($itemId);
        $item->setAccessKeyHash(null !== $key ? $this->accessKeyHasher->hash($key) : null);

        $this->itemRepository->save($item);

        return $item;
    }

    #[Override]
    public function unlockCategory(int $categoryId, string $key, Request $request): AccessGrant
    {
        $this->accessKeyRateLimiter->consume($request);

        $category = $this->categoryService->getById($categoryId);
        $holder = $this->findEffectiveKeyHolder($category);
        $holderKeyHash = $holder?->getAccessKeyHash();

        if (null === $holder || null === $holderKeyHash) {
            throw new BadRequestException(message: 'access_key.not_protected');
        }

        $this->assertKeyMatches($key, $holderKeyHash);

        return $this->sign(AccessKeyResource::forCategory($holder->getId(), $holder->getAccessKeyVersion(), $this->currentUserId()));
    }

    #[Override]
    public function unlockItem(int $itemId, string $key, Request $request): AccessGrant
    {
        $this->accessKeyRateLimiter->consume($request);

        $item = $this->itemService->getById($itemId);
        $itemKeyHash = $item->getAccessKeyHash();

        if (null === $itemKeyHash) {
            throw new BadRequestException(message: 'access_key.not_protected');
        }

        $this->assertKeyMatches($key, $itemKeyHash);

        return $this->sign(AccessKeyResource::forItem($item->getId(), $item->getAccessKeyVersion(), $this->currentUserId()));
    }

    /**
     * @throws UnauthorizedException
     */
    private function assertKeyMatches(string $key, string $hash): void
    {
        if (false === $this->accessKeyHasher->verify($key, $hash)) {
            throw new UnauthorizedException(message: 'access_key.invalid');
        }
    }

    private function sign(string $resource): AccessGrant
    {
        $signed = $this->signedUrlService->sign($resource, self::GRANT_TTL_SECONDS);

        return new AccessGrant(resource: $resource, expires: $signed['expires'], signature: $signed['signature']);
    }
}
