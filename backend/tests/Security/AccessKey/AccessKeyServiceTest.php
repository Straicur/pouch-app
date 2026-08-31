<?php

declare(strict_types = 1);

namespace App\Tests\Security\AccessKey;

use App\Category\CategoryServiceInterface;
use App\Entity\Category;
use App\Item\ItemServiceInterface;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Security\AccessKey\AccessKeyHasher;
use App\Security\AccessKey\AccessKeyService;
use App\Security\AccessKeyRateLimiterInterface;
use App\Security\SignedUrlServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * findEffectiveKeyHolder() only ever walks the entity graph (Category::getParent()),
 * so it's tested here against plain, unpersisted Category objects — no kernel/DB
 * needed. The other collaborators are stubbed since this method never touches them.
 */
final class AccessKeyServiceTest extends TestCase
{
    private function service(): AccessKeyService
    {
        return new AccessKeyService(
            categoryService: $this->createStub(CategoryServiceInterface::class),
            itemService: $this->createStub(ItemServiceInterface::class),
            categoryRepository: $this->createStub(CategoryRepository::class),
            itemRepository: $this->createStub(ItemRepository::class),
            accessKeyHasher: new AccessKeyHasher(),
            signedUrlService: $this->createStub(SignedUrlServiceInterface::class),
            accessKeyRateLimiter: $this->createStub(AccessKeyRateLimiterInterface::class),
            security: $this->createStub(Security::class),
        );
    }

    public function testSubcategoryWithoutOwnKeyInheritsFromParent(): void
    {
        $root = new Category('Root');
        $root->setAccessKeyHash('root-hash');
        $child = new Category('Child', $root);

        self::assertSame($root, $this->service()->findEffectiveKeyHolder($child));
    }

    public function testSubcategoryInheritsFromGrandparentThroughAnUnkeyedParent(): void
    {
        $grandparent = new Category('Grandparent');
        $grandparent->setAccessKeyHash('grandparent-hash');
        $parent = new Category('Parent', $grandparent);
        $child = new Category('Child', $parent);

        self::assertSame($grandparent, $this->service()->findEffectiveKeyHolder($child));
    }

    public function testSubcategoryWithItsOwnKeyDoesNotInheritFromParent(): void
    {
        $root = new Category('Root');
        $root->setAccessKeyHash('root-hash');
        $child = new Category('Child', $root);
        $child->setAccessKeyHash('child-hash');

        self::assertSame($child, $this->service()->findEffectiveKeyHolder($child));
    }

    public function testNoKeyAnywhereInTheChainReturnsNull(): void
    {
        $root = new Category('Root');
        $child = new Category('Child', $root);

        self::assertNull($this->service()->findEffectiveKeyHolder($child));
    }
}
