<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Email;

class TestRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'not_blank')]
        #[Email(
            message: 'email',
            mode: Email::VALIDATION_MODE_STRICT,
        )]
        private readonly string $email,
    ) {}

    public function getEmail(): string
    {
        return $this->email;
    }
}
