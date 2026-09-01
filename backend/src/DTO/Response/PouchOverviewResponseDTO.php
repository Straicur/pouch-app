<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class PouchOverviewResponseDTO
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly int $userCount,
        private readonly int $categoryCount,
        private readonly int $itemCount,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUserCount(): int
    {
        return $this->userCount;
    }

    public function getCategoryCount(): int
    {
        return $this->categoryCount;
    }

    public function getItemCount(): int
    {
        return $this->itemCount;
    }
}
