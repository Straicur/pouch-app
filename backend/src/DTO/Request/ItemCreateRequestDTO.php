<?php

declare(strict_types = 1);

namespace App\DTO\Request;

use App\Enum\TtlPreset;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Built manually from multipart form fields in ItemController (not deserialized
 * from a JSON body like the other Request DTOs — see RequestServiceInterface::validate()).
 */
class ItemCreateRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'not_blank')]
        #[Assert\Positive(message: 'positive')]
        private readonly int $categoryId,
        #[Assert\Length(max: 255, maxMessage: 'max_length')]
        private readonly ?string $name,
        // Optional free-text description, only actually used by createFile()
        // today (see ItemServiceInterface::createFile()'s own doc comment);
        // harmless as null for createUrl()/createPhoto(), which just ignore it.
        private readonly ?string $content,
        private readonly bool $keepForever,
        #[Assert\Choice(callback: [TtlPreset::class, 'values'], message: 'invalid_choice')]
        private readonly ?string $ttlPreset,
        // ISO 8601 string, parsed by the controller — kept as a string here so
        // an invalid format fails as a 400 (bad request), not a 422 (a
        // validation library reading the format wrong would be misleading).
        private readonly ?string $expiresAt,
        // Same deal as $content: only createFile() actually uses this today,
        // harmless as [] for createUrl()/createPhoto().
        /**
         * @var list<string>
         */
        #[Assert\Count(max: 20, maxMessage: 'tag.too_many')]
        #[Assert\All([
            new Assert\Type('string', message: 'tag.invalid_type'),
            new Assert\Length(max: 50, maxMessage: 'tag.too_long'),
        ])]
        private readonly array $tags = [],
    ) {}

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getContent(): ?string
    {
        return $this->content;
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

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }
}
