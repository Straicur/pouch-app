<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class ItemUpdateNoteRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'not_blank')]
        private readonly string $content,
    ) {}

    public function getContent(): string
    {
        return $this->content;
    }
}
