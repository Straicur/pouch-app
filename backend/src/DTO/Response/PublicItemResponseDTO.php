<?php

declare(strict_types = 1);

namespace App\DTO\Response;

/**
 * What a Part 9 public link reveals to someone with no account — deliberately
 * a subset of ItemResponseDTO: no categoryId/tags/favorite/processingStatus/
 * lifecycle fields, none of which mean anything (or are anyone else's
 * business) outside the app itself.
 */
class PublicItemResponseDTO
{
    public function __construct(
        private readonly int $id,
        private readonly string $type,
        private readonly string $name,
        private readonly ?string $originalFilename,
        private readonly ?string $mimeType,
        private readonly ?int $size,
        private readonly bool $hasThumbnail,
        private readonly ?string $url,
        private readonly ?string $pageTitle,
        private readonly ?string $pageDescription,
        private readonly ?string $noteContent,
        private readonly string $createdAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function isHasThumbnail(): bool
    {
        return $this->hasThumbnail;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getPageTitle(): ?string
    {
        return $this->pageTitle;
    }

    public function getPageDescription(): ?string
    {
        return $this->pageDescription;
    }

    public function getNoteContent(): ?string
    {
        return $this->noteContent;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
