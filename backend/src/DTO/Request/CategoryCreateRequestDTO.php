<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CategoryCreateRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'not_blank')]
        #[Assert\Length(max: 255, maxMessage: 'max_length')]
        private readonly string $name,
        private readonly ?int $parentId = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }
}
