<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Category;
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
     * Scoped to the current pouch by PouchFilter (see PouchFilterListener) —
     * not a query-time parameter here.
     *
     * @return list<Category>
     */
    public function findAllOrderedByName(): array
    {
        return array_values($this->findBy([], ['name' => 'ASC']));
    }

    /**
     * Part 10: "eksport/backup całości jako ZIP" — the whole tree is every
     * category with no parent, plus (recursively, via Category::getChildren())
     * everything under each of them.
     *
     * $pouchId scopes to one pouch when given — used by the admin backup's
     * per-pouch mode (CategoryExportService::buildFullBackupZip()).
     *
     * @return list<Category>
     */
    public function findRootCategories(?int $pouchId = null): array
    {
        $criteria = ['parent' => null];
        if (null !== $pouchId) {
            $criteria['pouch'] = $pouchId;
        }

        return array_values($this->findBy($criteria, ['name' => 'ASC']));
    }

    /**
     * Scalar rows, not full Category entities — AccessKeyGuard::
     * lockedCategoryIds() calls this on *every* GET /api/items and only ever
     * looks at id/parent/access-key fields, so hydrating full entities would
     * be needless weight. Just enough to walk the parent chain in PHP the
     * same way AccessKeyService::findEffectiveKeyHolder() does for one
     * category at a time.
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
