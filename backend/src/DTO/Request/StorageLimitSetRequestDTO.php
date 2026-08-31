<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class StorageLimitSetRequestDTO
{
    public function __construct(
        #[Assert\Positive(message: 'positive')]
        private readonly int $maxSizeBytes,
    ) {}

    public function getMaxSizeBytes(): int
    {
        return $this->maxSizeBytes;
    }
}
