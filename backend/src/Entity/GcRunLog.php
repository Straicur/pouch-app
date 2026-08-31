<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Repository\GcRunLogRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Part 10's GC dashboard: "podgląd automatycznego czyszczenia" + "logi tego,
 * co i kiedy zostało usunięte". One row per full GC cycle (expire-overdue +
 * purge-trash together — see ItemGarbageCollectorInterface::run(), the one
 * thing that writes these) — $trigger distinguishes the real cron
 * (`app:item:gc`) from an admin's manual "Run GC Now". *Which* items were
 * purged is deliberately not repeated here — that's exactly what
 * AuditLog::ACTION_PURGE rows already capture, per item, at the same moment.
 */
#[ORM\Entity(repositoryClass: GcRunLogRepository::class)]
#[ORM\Table(name: 'gc_run_log')]
class GcRunLog
{
    public const string TRIGGER_CRON = 'cron';

    public const string TRIGGER_MANUAL = 'manual';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'gc_run_log_id', type: Types::INTEGER, unique: true, nullable: false, options: ['unsigned' => true])]
    private int $id;

    #[ORM\Column(name: 'trigger', type: Types::STRING, length: 16, nullable: false)]
    private string $trigger;

    #[ORM\Column(name: 'expired_count', type: Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $expiredCount;

    #[ORM\Column(name: 'purged_count', type: Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $purgedCount;

    #[ORM\Column(name: 'run_at', type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private DateTimeImmutable $runAt;

    public function __construct(string $trigger, int $expiredCount, int $purgedCount)
    {
        $this->trigger = $trigger;
        $this->expiredCount = $expiredCount;
        $this->purgedCount = $purgedCount;
        $this->runAt = new DateTimeImmutable();
    }

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

    public function getRunAt(): DateTimeImmutable
    {
        return $this->runAt;
    }
}
