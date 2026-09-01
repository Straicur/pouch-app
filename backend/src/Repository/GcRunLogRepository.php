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
     * $pouchId omitted returns every run (cron sweeps and any manual run,
     * across every pouch); given, only manual runs scoped to that pouch —
     * a cron sweep's own GcRunLog row always has pouch = null (see
     * ItemGarbageCollector::run()), so it never matches a specific pouch id.
     *
     * @return list<GcRunLog> newest first
     */
    public function findLatest(int $limit, ?int $pouchId = null): array
    {
        $criteria = null !== $pouchId ? ['pouch' => $pouchId] : [];

        return array_values($this->findBy($criteria, ['runAt' => 'DESC'], $limit));
    }
}
