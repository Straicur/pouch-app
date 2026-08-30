<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CategoryRenameRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'not_blank')]
        #[Assert\Length(max: 255, maxMessage: 'max_length')]
        private readonly string $name,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }
}
