<?php

declare(strict_types=1);

namespace App\Tests\DTO;

final readonly class UserTestDTO
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private string $email,
        private string $password,
        private array $roles = ['ROLE_USER'],
    )
    {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }
}
