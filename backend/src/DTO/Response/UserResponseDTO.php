<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class UserResponseDTO
{
    public function __construct(
        private readonly int $id,
        private readonly string $email,
        private readonly string $role,
        private readonly bool $enabled,
        private readonly int $pouchId,
        private readonly string $pouchName,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getPouchId(): int
    {
        return $this->pouchId;
    }

    public function getPouchName(): string
    {
        return $this->pouchName;
    }
}
