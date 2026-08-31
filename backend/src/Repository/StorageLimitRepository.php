<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\StorageLimit;
use App\Enum\ItemType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StorageLimit>
 */
class StorageLimitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StorageLimit::class);
    }

    public function save(StorageLimit $storageLimit): void
    {
        $this->getEntityManager()->persist($storageLimit);
        $this->getEntityManager()->flush();
    }

    public function findByType(ItemType $type): ?StorageLimit
    {
        return $this->find($type);
    }
}
