<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use App\Enum\TtlPreset;
use Symfony\Component\Validator\Constraints as Assert;

class ItemCreateNoteRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'not_blank')]
        #[Assert\Positive(message: 'positive')]
        private readonly int $categoryId,
        #[Assert\NotBlank(message: 'not_blank')]
        private readonly string $content,
        #[Assert\Length(max: 255, maxMessage: 'max_length')]
        private readonly ?string $name = null,
        private readonly bool $keepForever = false,
        #[Assert\Choice(callback: [TtlPreset::class, 'values'], message: 'invalid_choice')]
        private readonly ?string $ttlPreset = null,
        private readonly ?string $expiresAt = null,
    ) {}

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function isKeepForever(): bool
    {
        return $this->keepForever;
    }

    public function getTtlPreset(): ?string
    {
        return $this->ttlPreset;
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }
}
