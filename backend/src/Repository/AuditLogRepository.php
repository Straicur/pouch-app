<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function save(AuditLog $auditLog): void
    {
        $this->getEntityManager()->persist($auditLog);
        $this->getEntityManager()->flush();
    }

    /**
     * @return list<AuditLog> newest first
     */
    public function findLatest(int $limit, ?string $resourceType, ?string $action): array
    {
        $qb = $this->createQueryBuilder('a')->orderBy('a.createdAt', 'DESC')->setMaxResults($limit);

        if (null !== $resourceType) {
            $qb->andWhere('a.resourceType = :resourceType')->setParameter('resourceType', $resourceType);
        }

        if (null !== $action) {
            $qb->andWhere('a.action = :action')->setParameter('action', $action);
        }

        /** @var list<AuditLog> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
