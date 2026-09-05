<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class TechnicalBreakEnableRequestDTO
{
    public function __construct(
        #[Assert\Length(max: 2000, maxMessage: 'max_length')]
        private readonly ?string $message = null,
    ) {}

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
