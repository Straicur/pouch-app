<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class ItemMoveRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'not_blank')]
        #[Assert\Positive(message: 'positive')]
        private readonly int $categoryId,
    ) {}

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }
}
