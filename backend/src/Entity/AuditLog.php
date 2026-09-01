<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Repository\AuditLogRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(name: 'idx_audit_log_created_at', fields: ['createdAt'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'audit_log_id', type: Types::INTEGER, unique: true, nullable: false, options: ['unsigned' => true])]
    private int $id;

    #[ORM\Column(name: 'action', type: Types::STRING, length: 32, nullable: false)]
    private string $action;

    #[ORM\Column(name: 'resource_type', type: Types::STRING, length: 32, nullable: false)]
    private string $resourceType;

    #[ORM\Column(name: 'resource_id', type: Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $resourceId;

    #[ORM\Column(name: 'user_id', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $userId;

    #[ORM\Column(name: 'user_email', type: Types::STRING, length: 255, nullable: true)]
    private ?string $userEmail;

    #[ORM\Column(name: 'ip', type: Types::STRING, length: 45, nullable: true)]
    private ?string $ip;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Pouch::class)]
    #[ORM\JoinColumn(name: 'pouch_id', referencedColumnName: 'pouch_id', nullable: true, onDelete: 'SET NULL')]
    private ?Pouch $pouch;

    public function __construct(
        string $action,
        string $resourceType,
        int $resourceId,
        ?User $user,
        ?string $ip,
        ?Pouch $pouch = null,
    ) {
        $this->action = $action;
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
        $this->userId = $user?->getId();
        $this->userEmail = $user?->getEmail();
        $this->ip = $ip;
        $this->createdAt = new DateTimeImmutable();
        $this->pouch = $pouch;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId(): int
    {
        return $this->resourceId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPouch(): ?Pouch
    {
        return $this->pouch;
    }
}
