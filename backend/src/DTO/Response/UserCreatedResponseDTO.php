<?php

declare(strict_types = 1);

namespace App\DTO\Response;

/**
 * The temporary password is only ever returned here — once, right after
 * generation (create/reset-password) — never stored anywhere retrievable
 * (User only ever holds its hash) and never included in UserResponseDTO's
 * regular listing shape.
 */
class UserCreatedResponseDTO
{
    public function __construct(
        private readonly UserResponseDTO $user,
        private readonly string $temporaryPassword,
    ) {}

    public function getUser(): UserResponseDTO
    {
        return $this->user;
    }

    public function getTemporaryPassword(): string
    {
        return $this->temporaryPassword;
    }
}
