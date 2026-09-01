<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use App\ControllerHelper\Enum\UserRole;
use Symfony\Component\Validator\Constraints as Assert;

class UserRoleUpdateRequestDTO
{
    private const array ROLES = [UserRole::GUEST->value, UserRole::USER->value, UserRole::ADMIN->value];

    public function __construct(
        #[Assert\Choice(choices: self::ROLES, message: 'invalid_choice')]
        private readonly string $role,
    ) {}

    public function getRole(): string
    {
        return $this->role;
    }
}
