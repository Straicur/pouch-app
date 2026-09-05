<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\TechnicalBreak;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TechnicalBreak>
 */
class TechnicalBreakRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TechnicalBreak::class);
    }

    public function save(TechnicalBreak $technicalBreak): void
    {
        $this->getEntityManager()->persist($technicalBreak);
        $this->getEntityManager()->flush();
    }

    public function findActive(): ?TechnicalBreak
    {
        return $this->findOneBy(['active' => true]);
    }
}
