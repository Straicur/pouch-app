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
}
