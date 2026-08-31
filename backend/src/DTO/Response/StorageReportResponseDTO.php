<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class StorageReportResponseDTO
{
    /**
     * @param list<StorageUsageByTypeResponseDTO> $byType
     * @param list<StorageLimitResponseDTO>       $limits
     */
    public function __construct(
        private readonly array $byType,
        private readonly int $archivedVersionsBytes,
        private readonly array $limits,
    ) {}

    /**
     * @return list<StorageUsageByTypeResponseDTO>
     */
    public function getByType(): array
    {
        return $this->byType;
    }

    public function getArchivedVersionsBytes(): int
    {
        return $this->archivedVersionsBytes;
    }

    /**
     * @return list<StorageLimitResponseDTO>
     */
    public function getLimits(): array
    {
        return $this->limits;
    }
}
