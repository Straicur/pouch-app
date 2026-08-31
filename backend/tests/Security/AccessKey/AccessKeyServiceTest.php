<?php

declare(strict_types = 1);

namespace App\Tests\Security\AccessKey;

use App\Services\Category\CategoryServiceInterface;
use App\Entity\Category;
use App\Entity\Pouch;
use App\Services\Item\ItemServiceInterface;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Security\AccessKey\AccessKeyHasher;
use App\Security\AccessKey\AccessKeyService;
use App\Security\Limiter\AccessKeyRateLimiterInterface;
use App\Security\SignedUrlServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

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
            accessKeyHasher: new AccessKeyHasher($this->createStub(PasswordHasherFactoryInterface::class)),
            signedUrlService: $this->createStub(SignedUrlServiceInterface::class),
            accessKeyRateLimiter: $this->createStub(AccessKeyRateLimiterInterface::class),
            security: $this->createStub(Security::class),
        );
    }

    private function pouch(): Pouch
    {
        return new Pouch('Test pouch');
    }

    public function testSubcategoryWithoutOwnKeyInheritsFromParent(): void
    {
        $pouch = $this->pouch();
        $root = new Category('Root', $pouch);
        $root->setAccessKeyHash('root-hash');
        $child = new Category('Child', $pouch, $root);

        self::assertSame($root, $this->service()->findEffectiveKeyHolder($child));
    }

    public function testSubcategoryInheritsFromGrandparentThroughAnUnkeyedParent(): void
    {
        $pouch = $this->pouch();
        $grandparent = new Category('Grandparent', $pouch);
        $grandparent->setAccessKeyHash('grandparent-hash');
        $parent = new Category('Parent', $pouch, $grandparent);
        $child = new Category('Child', $pouch, $parent);

        self::assertSame($grandparent, $this->service()->findEffectiveKeyHolder($child));
    }

    public function testSubcategoryWithItsOwnKeyDoesNotInheritFromParent(): void
    {
        $pouch = $this->pouch();
        $root = new Category('Root', $pouch);
        $root->setAccessKeyHash('root-hash');
        $child = new Category('Child', $pouch, $root);
        $child->setAccessKeyHash('child-hash');

        self::assertSame($child, $this->service()->findEffectiveKeyHolder($child));
    }

    public function testNoKeyAnywhereInTheChainReturnsNull(): void
    {
        $pouch = $this->pouch();
        $root = new Category('Root', $pouch);
        $child = new Category('Child', $pouch, $root);

        self::assertNull($this->service()->findEffectiveKeyHolder($child));
    }
}
