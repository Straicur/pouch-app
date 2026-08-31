<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Repository\AuditLogRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Part 10: "dziennik zdarzeń: kto, kiedy, z jakiego IP podejrzał/pobrał/
 * usunął item albo zmienił klucz dostępu". Append-only — nothing ever
 * updates or deletes a row here (not even when the underlying resource is
 * later purged/deleted, which is exactly the kind of event this exists to
 * remember) — so $resourceId is a plain int, not a real foreign key: the
 * resource it refers to may well be gone by the time anyone reads this row.
 *
 * $userId is nullable for actions with no logged-in actor — GC purges are
 * system-triggered, and a Part 9 public link's download has no auth token
 * at all — $ip is kept either way. Scope is deliberately bounded to what the
 * roadmap actually enumerates (view/download/delete/key_change/purge on
 * categories/items), not literally every request: logging every GET would
 * make this table's own size the thing that needs garbage collecting, which
 * the roadmap's own tech-requirements note flags as a real concern
 * ("warto pomyśleć o retencji/limicie wielkości tej tabeli") — retention
 * itself isn't implemented yet, deliberately left for whenever that becomes
 * an actual problem rather than a hypothetical one.
 */
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

    public function __construct(
        string $action,
        string $resourceType,
        int $resourceId,
        ?User $user,
        ?string $ip,
    ) {
        $this->action = $action;
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
        $this->userId = $user?->getId();
        $this->userEmail = $user?->getEmail();
        $this->ip = $ip;
        $this->createdAt = new DateTimeImmutable();
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
}
