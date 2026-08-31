<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Pouch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
}
