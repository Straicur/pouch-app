<?php

declare(strict_types = 1);

namespace App\DTO\Response;

class ItemResponseDTO
{
    public function __construct(
        private readonly int $id,
        private readonly int $categoryId,
        private readonly string $type,
        private readonly string $name,
        private readonly string $processingStatus,
        private readonly ?string $processingError,
        private readonly ?string $originalFilename,
        private readonly ?string $mimeType,
        private readonly ?int $size,
        private readonly bool $hasThumbnail,
        private readonly ?string $url,
        private readonly ?string $pageTitle,
        private readonly ?string $pageDescription,
        private readonly ?string $extractedText,
        private readonly ?string $noteContent,
        private readonly bool $favorite,
        /**
         * @var list<string>
         */
        private readonly array $tags,
        private readonly bool $keepForever,
        private readonly ?string $expiresAt,
        private readonly ?string $trashedAt,
        private readonly string $createdAt,
        // Część 13 — czy ten item ma ustawiony własny klucz dostępu.
        // Świadomie tylko tutaj (pełny detal, GET /api/items/{id}), nie na
        // ItemSummaryResponseDTO — do listy nie trzeba wiedzieć, czy klucz
        // istnieje, tylko czy request jest nim odblokowany (locked).
        // AccessKeyPanel używa tego, żeby pokazać "Ustaw klucz" albo
        // "Zmień/Usuń klucz", zamiast zawsze oferować oba warianty naraz.
        private readonly bool $hasAccessKey,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProcessingStatus(): string
    {
        return $this->processingStatus;
    }

    public function getProcessingError(): ?string
    {
        return $this->processingError;
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

    public function getExtractedText(): ?string
    {
        return $this->extractedText;
    }

    public function getNoteContent(): ?string
    {
        return $this->noteContent;
    }

    public function isFavorite(): bool
    {
        return $this->favorite;
    }

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function isKeepForever(): bool
    {
        return $this->keepForever;
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    public function getTrashedAt(): ?string
    {
        return $this->trashedAt;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function isHasAccessKey(): bool
    {
        return $this->hasAccessKey;
    }
}
