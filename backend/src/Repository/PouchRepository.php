<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Pouch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use function is_numeric;

/**
 * @extends ServiceEntityRepository<Pouch>
 */
class PouchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pouch::class);
    }

    public function save(Pouch $pouch): void
    {
        $this->getEntityManager()->persist($pouch);
        $this->getEntityManager()->flush();
    }

    /**
     * @return list<Pouch>
     */
    public function findAllOrderedByName(): array
    {
        /** @var list<Pouch> $result */
        $result = $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * How many users/categories/active items belong to each pouch — admin's
     * pouch overview. Three scalar subqueries rather than LEFT JOINs, so
     * counting one relation never fans out the row count for another.
     *
     * @return array<int, array{userCount: int, categoryCount: int, itemCount: int}> keyed by pouch id
     */
    public function countsByPouchId(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(<<<'SQL'
            SELECT
                p.pouch_id AS id,
                (SELECT COUNT(*) FROM "user" u WHERE u.pouch_id = p.pouch_id) AS user_count,
                (SELECT COUNT(*) FROM category c WHERE c.pouch_id = p.pouch_id) AS category_count,
                (SELECT COUNT(*) FROM item i WHERE i.pouch_id = p.pouch_id AND i.trashed_at IS NULL) AS item_count
            FROM pouch p
            SQL);

        $counts = [];
        foreach ($rows as $row) {
            $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
            $counts[$id] = [
                'userCount'     => is_numeric($row['user_count'] ?? null) ? (int) $row['user_count'] : 0,
                'categoryCount' => is_numeric($row['category_count'] ?? null) ? (int) $row['category_count'] : 0,
                'itemCount'     => is_numeric($row['item_count'] ?? null) ? (int) $row['item_count'] : 0,
            ];
        }

        return $counts;
    }
}
