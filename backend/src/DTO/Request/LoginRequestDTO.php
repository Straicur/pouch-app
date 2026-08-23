<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use SensitiveParameter;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Email;

class LoginRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'not_blank')]
        #[Email(
            message: 'email',
            mode: Email::VALIDATION_MODE_STRICT,
        )]
        private readonly string $email,
        #[SensitiveParameter]
        #[Assert\NotBlank(message: 'not_blank')]
        private readonly string $password,
    ) {}

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
