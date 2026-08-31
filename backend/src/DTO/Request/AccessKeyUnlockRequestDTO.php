<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class AccessKeyUnlockRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'not_blank')]
        #[Assert\Length(max: 255, maxMessage: 'max_length')]
        private readonly string $key,
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }
}
