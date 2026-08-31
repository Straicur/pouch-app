<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Pouch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use function array_map;
use function array_values;
use function is_numeric;
use function is_string;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function save(Category $category): void
    {
        $this->getEntityManager()->persist($category);
        $this->getEntityManager()->flush();
    }

    public function remove(Category $category): void
    {
        $this->getEntityManager()->remove($category);
        $this->getEntityManager()->flush();
    }

    /**
     * @return list<Category>
     */
    public function findAllForPouchOrderedByName(Pouch $pouch): array
    {
        return array_values($this->findBy(['pouch' => $pouch], ['name' => 'ASC']));
    }

    /**
     * Part 10: "eksport/backup całości jako ZIP" — the whole tree is every
     * category with no parent, plus (recursively, via Category::getChildren())
     * everything under each of them.
     *
     * @return list<Category>
     */
    public function findRootCategories(): array
    {
        return array_values($this->findBy(['parent' => null], ['name' => 'ASC']));
    }

    /**
     * Post-review fix: AccessKeyGuard::lockedCategoryIds() used to hydrate
     * every full Category entity (via findAllOrderedByName()) on *every*
     * GET /api/items — fine for a personal-scale category tree, but full
     * entities are needless weight for a check that only ever looks at
     * id/parent/access-key fields. Scalar rows instead, with just enough to
     * walk the parent chain in PHP the same way
     * AccessKeyService::findEffectiveKeyHolder() does for one category at a
     * time.
     *
     * @return list<array{id: int, parentId: int|null, accessKeyHash: string|null, accessKeyVersion: int}>
     */
    public function findAllForLockCheck(): array
    {
        /** @var list<array{id: mixed, parentId: mixed, accessKeyHash: mixed, accessKeyVersion: mixed}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('c.id AS id', 'IDENTITY(c.parent) AS parentId', 'c.accessKeyHash AS accessKeyHash', 'c.accessKeyVersion AS accessKeyVersion')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'id'               => is_numeric($row['id']) ? (int) $row['id'] : 0,
            'parentId'         => is_numeric($row['parentId']) ? (int) $row['parentId'] : null,
            'accessKeyHash'    => is_string($row['accessKeyHash']) ? $row['accessKeyHash'] : null,
            'accessKeyVersion' => is_numeric($row['accessKeyVersion']) ? (int) $row['accessKeyVersion'] : 0,
        ], $rows);
    }
}
