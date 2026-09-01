<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Repository\GcRunLogRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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

    #[ORM\ManyToOne(targetEntity: Pouch::class)]
    #[ORM\JoinColumn(name: 'pouch_id', referencedColumnName: 'pouch_id', nullable: true, onDelete: 'SET NULL')]
    private ?Pouch $pouch;

    public function __construct(string $trigger, int $expiredCount, int $purgedCount, ?Pouch $pouch = null)
    {
        $this->trigger = $trigger;
        $this->expiredCount = $expiredCount;
        $this->purgedCount = $purgedCount;
        $this->runAt = new DateTimeImmutable();
        $this->pouch = $pouch;
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

    public function getPouch(): ?Pouch
    {
        return $this->pouch;
    }
}
