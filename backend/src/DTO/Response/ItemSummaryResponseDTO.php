<?php

declare(strict_types = 1);

namespace App\DTO\Response;

/**
 * Post-review fix: GET /api/items (paginated) used to reuse ItemResponseDTO
 * — every item's full $extractedText (OCR output for photos, scraped page
 * text for URL items) rode along on every list request even though nothing
 * in the frontend renders it there, ballooning the payload well past what
 * "mobile-first" implies at any real collection size. Same shape as
 * ItemResponseDTO minus that one field; $noteContent stays, since ItemCard
 * genuinely renders a note's full body inline in the list, not behind a
 * separate "view details" fetch.
 */
class ItemSummaryResponseDTO
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
        private readonly bool $locked,
        // Set only when the list was filtered by a free-text query and a
        // fragment of this item's own text (not just a tag) matched it — see
        // ItemRepository::findSnippets(). The matched part is wrapped in
        // ItemRepository::SNIPPET_HIGHLIGHT_START/END, not HTML — the
        // frontend renders it as plain text segments, never raw markup.
        private readonly ?string $snippet = null,
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

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function getSnippet(): ?string
    {
        return $this->snippet;
    }
}
