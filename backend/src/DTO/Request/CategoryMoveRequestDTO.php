<?php

declare(strict_types = 1);

namespace App\DTO\Request;

class CategoryMoveRequestDTO
{
    public function __construct(
        // null = move to the root (no parent).
        private readonly ?int $parentId = null,
    ) {}

    public function getParentId(): ?int
    {
        return $this->parentId;
    }
}
