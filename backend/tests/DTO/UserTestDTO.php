<?php

declare(strict_types=1);

namespace App\Tests\DTO;

final readonly class UserTestDTO
{
    public function __construct(
        private string $email,
        private string $password,
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
}
