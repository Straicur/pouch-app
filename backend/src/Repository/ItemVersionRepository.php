<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Item;
use App\Entity\ItemVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemVersion>
 */
class ItemVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemVersion::class);
    }

    public function save(ItemVersion $itemVersion): void
    {
        $this->getEntityManager()->persist($itemVersion);
        $this->getEntityManager()->flush();
    }

    /**
     * @return list<ItemVersion>
     */
    public function findByItemOrderedByVersion(Item $item): array
    {
        return array_values($this->findBy(['item' => $item], ['version' => 'ASC']));
    }

    public function findOneByItemAndVersion(Item $item, int $version): ?ItemVersion
    {
        return $this->findOneBy(['item' => $item, 'version' => $version]);
    }

    /**
     * Part 10 storage dashboard: archived versions occupy real storage too
     * (that's the whole point of not deleting them — see ItemService::
     * overwriteFile()), so they count toward total usage even though they're
     * not reflected in Item::$size for any single item.
     */
    public function sumSize(): int
    {
        $total = $this->createQueryBuilder('v')
            ->select('SUM(v.size)')
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $total ? (int) $total : 0;
    }
}
