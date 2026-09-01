<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class GcRunLogResponseDTO
{
    public function __construct(
        private readonly int $id,
        private readonly string $trigger,
        private readonly int $expiredCount,
        private readonly int $purgedCount,
        private readonly string $runAt,
        // null for a cron sweep (every pouch) or a manual run with no pouch given.
        private readonly ?string $pouchName,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getTrigger(): string
    {
        return $this->trigger;
    }

    public function getExpiredCount(): int
    {
        return $this->expiredCount;
    }

    public function getPurgedCount(): int
    {
        return $this->purgedCount;
    }

    public function getRunAt(): string
    {
        return $this->runAt;
    }

    public function getPouchName(): ?string
    {
        return $this->pouchName;
    }
}
