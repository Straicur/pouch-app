<?php

declare(strict_types = 1);

namespace App\DTO\Mapper;

use App\DTO\Response\AuditLogResponseDTO;
use App\DTO\Response\GcRunLogResponseDTO;
use App\DTO\Response\StorageLimitResponseDTO;
use App\DTO\Response\StorageUsageByTypeResponseDTO;
use App\Entity\AuditLog;
use App\Entity\GcRunLog;
use DateTimeInterface;

use function array_map;

final class AdminMapper
{
    public static function toGcRunLogResponseDTO(GcRunLog $gcRunLog): GcRunLogResponseDTO
    {
        return new GcRunLogResponseDTO(
            id: $gcRunLog->getId(),
            trigger: $gcRunLog->getTrigger(),
            expiredCount: $gcRunLog->getExpiredCount(),
            purgedCount: $gcRunLog->getPurgedCount(),
            runAt: $gcRunLog->getRunAt()->format(DateTimeInterface::ATOM),
        );
    }

    /**
     * @param list<GcRunLog> $gcRunLogs
     *
     * @return list<GcRunLogResponseDTO>
     */
    public static function toGcRunLogResponseDTOList(array $gcRunLogs): array
    {
        return array_map(self::toGcRunLogResponseDTO(...), $gcRunLogs);
    }

    public static function toAuditLogResponseDTO(AuditLog $auditLog): AuditLogResponseDTO
    {
        return new AuditLogResponseDTO(
            id: $auditLog->getId(),
            action: $auditLog->getAction(),
            resourceType: $auditLog->getResourceType(),
            resourceId: $auditLog->getResourceId(),
            userId: $auditLog->getUserId(),
            userEmail: $auditLog->getUserEmail(),
            ip: $auditLog->getIp(),
            createdAt: $auditLog->getCreatedAt()->format(DateTimeInterface::ATOM),
        );
    }

    /**
     * @param list<AuditLog> $auditLogs
     *
     * @return list<AuditLogResponseDTO>
     */
    public static function toAuditLogResponseDTOList(array $auditLogs): array
    {
        return array_map(self::toAuditLogResponseDTO(...), $auditLogs);
    }

    /**
     * @param array<string, int> $maxSizeBytesByType
     *
     * @return list<StorageLimitResponseDTO>
     */
    public static function toStorageLimitResponseDTOList(array $maxSizeBytesByType): array
    {
        $limits = [];
        foreach ($maxSizeBytesByType as $type => $maxSizeBytes) {
            $limits[] = new StorageLimitResponseDTO(type: $type, maxSizeBytes: $maxSizeBytes);
        }

        return $limits;
    }

    /**
     * @param array<string, array{totalBytes: int, itemCount: int}> $usageByType
     *
     * @return list<StorageUsageByTypeResponseDTO>
     */
    public static function toStorageUsageResponseDTOList(array $usageByType): array
    {
        $usage = [];
        foreach ($usageByType as $type => $row) {
            $usage[] = new StorageUsageByTypeResponseDTO(type: $type, totalBytes: $row['totalBytes'], itemCount: $row['itemCount']);
        }

        return $usage;
    }
}
