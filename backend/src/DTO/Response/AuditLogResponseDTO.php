<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class AuditLogResponseDTO
{
    public function __construct(
        private readonly int $id,
        private readonly string $action,
        private readonly string $resourceType,
        private readonly int $resourceId,
        private readonly ?int $userId,
        private readonly ?string $userEmail,
        private readonly ?string $ip,
        private readonly string $createdAt,
        // null for an action with no single owning pouch (rare — every
        // resourceType has one today; kept nullable for whatever doesn't, later).
        private readonly ?string $pouchName,
    ) {}

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

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getPouchName(): ?string
    {
        return $this->pouchName;
    }
}
