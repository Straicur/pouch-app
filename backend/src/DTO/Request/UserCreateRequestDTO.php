<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Email;

class UserCreateRequestDTO
{
    private const array ROLES = ['ROLE_GUEST', 'ROLE_USER', 'ROLE_ADMIN'];

    public function __construct(
        #[Assert\NotBlank(message: 'not_blank')]
        #[Email(message: 'email', mode: Email::VALIDATION_MODE_STRICT)]
        private readonly string $email,
        #[Assert\Choice(choices: self::ROLES, message: 'invalid_choice')]
        private readonly string $role,
        private readonly ?int $pouchId = null,
        #[Assert\Length(max: 255, maxMessage: 'max_length')]
        private readonly ?string $newPouchName = null,
    ) {}

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getPouchId(): ?int
    {
        return $this->pouchId;
    }

    public function getNewPouchName(): ?string
    {
        return $this->newPouchName;
    }
}
