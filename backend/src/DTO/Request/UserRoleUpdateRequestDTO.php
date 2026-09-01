<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class UserRoleUpdateRequestDTO
{
    private const array ROLES = ['ROLE_GUEST', 'ROLE_USER', 'ROLE_ADMIN'];

    public function __construct(
        #[Assert\Choice(choices: self::ROLES, message: 'invalid_choice')]
        private readonly string $role,
    ) {}

    public function getRole(): string
    {
        return $this->role;
    }
}
