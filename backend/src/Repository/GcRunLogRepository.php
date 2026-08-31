<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\GcRunLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GcRunLog>
 */
class GcRunLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GcRunLog::class);
    }

    public function save(GcRunLog $gcRunLog): void
    {
        $this->getEntityManager()->persist($gcRunLog);
        $this->getEntityManager()->flush();
    }

    /**
     * @return list<GcRunLog> newest first
     */
    public function findLatest(int $limit): array
    {
        return array_values($this->findBy([], ['runAt' => 'DESC'], $limit));
    }
}
