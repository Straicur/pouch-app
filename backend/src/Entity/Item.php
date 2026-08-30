<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Enum\ItemProcessingStatus;
use App\Enum\ItemType;
use App\Repository\ItemRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One entity for every item type (see ItemType). Fields only some types use
 * (e.g. $url for URL items, $storageKey for file/photo) are nullable rather
 * than split into per-type tables — simple while there are only three types
 * and no field is expensive to carry around unused.
 *
 * File/photo uploads are COMPLETED immediately; URL and photo items start
 * PENDING and are finished asynchronously (see ScrapeUrlMessageHandler /
 * ProcessPhotoMessageHandler) since scraping/OCR can't block the request.
 */
#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ORM\Table(name: 'item')]
#[ORM\Index(fields: ['contentHash'], name: 'idx_item_content_hash')]
#[ORM\Index(fields: ['expiresAt'], name: 'idx_item_expires_at')]
#[ORM\Index(fields: ['trashedAt'], name: 'idx_item_trashed_at')]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'item_id', type: Types::INTEGER, unique: true, nullable: false, options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'category_id', nullable: false, onDelete: 'CASCADE')]
    private Category $category;

    #[ORM\Column(name: 'type', enumType: ItemType::class)]
    private ItemType $type;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255, nullable: false)]
    private string $name;

    #[ORM\Column(name: 'processing_status', enumType: ItemProcessingStatus::class)]
    private ItemProcessingStatus $processingStatus;

    #[ORM\Column(name: 'processing_error', type: Types::TEXT, nullable: true)]
    private ?string $processingError = null;

    // --- file / photo primary asset (null for URL items) ---

    #[ORM\Column(name: 'original_filename', type: Types::STRING, length: 255, nullable: true)]
    private ?string $originalFilename = null;

    #[ORM\Column(name: 'mime_type', type: Types::STRING, length: 255, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(name: 'size', type: Types::BIGINT, nullable: true, options: ['unsigned' => true])]
    private ?int $size = null;

    #[ORM\Column(name: 'storage_key', type: Types::STRING, length: 512, unique: true, nullable: true)]
    private ?string $storageKey = null;

    #[ORM\Column(name: 'content_hash', type: Types::STRING, length: 64, nullable: true)]
    private ?string $contentHash = null;

    // --- photo/URL secondary asset + metadata ---

    #[ORM\Column(name: 'thumbnail_storage_key', type: Types::STRING, length: 512, unique: true, nullable: true)]
    private ?string $thumbnailStorageKey = null;

    #[ORM\Column(name: 'url', type: Types::STRING, length: 2048, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(name: 'page_title', type: Types::STRING, length: 255, nullable: true)]
    private ?string $pageTitle = null;

    #[ORM\Column(name: 'page_description', type: Types::TEXT, nullable: true)]
    private ?string $pageDescription = null;

    /**
     * OpenGraph page snapshot text (URL items) or OCR output (photo items) —
     * shared column since both are "the searchable text this item produced",
     * feeding the same Part 6 tsvector index either way.
     */
    #[ORM\Column(name: 'extracted_text', type: Types::TEXT, nullable: true)]
    private ?string $extractedText = null;

    /**
     * Note items only — the raw markdown source, user-writable and editable
     * after the fact (product doc). Its own field, not $extractedText: this is
     * the note's actual content, not text derived *from* something else.
     */
    #[ORM\Column(name: 'note_content', type: Types::TEXT, nullable: true)]
    private ?string $noteContent = null;

    // --- lifecycle (Part 3) ---

    #[ORM\Column(name: 'keep_forever', type: Types::BOOLEAN, nullable: false)]
    private bool $keepForever;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'trashed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $trashedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private DateTimeImmutable $createdAt;

    public function __construct(
        Category $category,
        ItemType $type,
        string $name,
        bool $keepForever,
        ?DateTimeImmutable $expiresAt,
        ItemProcessingStatus $processingStatus,
    ) {
        $this->category = $category;
        $this->type = $type;
        $this->name = $name;
        $this->keepForever = $keepForever;
        $this->expiresAt = $expiresAt;
        $this->processingStatus = $processingStatus;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function getType(): ItemType
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getProcessingStatus(): ItemProcessingStatus
    {
        return $this->processingStatus;
    }

    public function getProcessingError(): ?string
    {
        return $this->processingError;
    }

    public function markCompleted(): static
    {
        $this->processingStatus = ItemProcessingStatus::COMPLETED;
        $this->processingError = null;

        return $this;
    }

    public function markFailed(string $error): static
    {
        $this->processingStatus = ItemProcessingStatus::FAILED;
        $this->processingError = $error;

        return $this;
    }

    public function setFileData(
        string $originalFilename,
        string $mimeType,
        int $size,
        string $storageKey,
        string $contentHash,
    ): static {
        $this->originalFilename = $originalFilename;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->storageKey = $storageKey;
        $this->contentHash = $contentHash;

        return $this;
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

    public function getStorageKey(): ?string
    {
        return $this->storageKey;
    }

    public function getContentHash(): ?string
    {
        return $this->contentHash;
    }

    public function setThumbnailStorageKey(?string $thumbnailStorageKey): static
    {
        $this->thumbnailStorageKey = $thumbnailStorageKey;

        return $this;
    }

    public function getThumbnailStorageKey(): ?string
    {
        return $this->thumbnailStorageKey;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setPageMetadata(?string $pageTitle, ?string $pageDescription): static
    {
        $this->pageTitle = $pageTitle;
        $this->pageDescription = $pageDescription;

        return $this;
    }

    public function getPageTitle(): ?string
    {
        return $this->pageTitle;
    }

    public function getPageDescription(): ?string
    {
        return $this->pageDescription;
    }

    public function setExtractedText(?string $extractedText): static
    {
        $this->extractedText = $extractedText;

        return $this;
    }

    public function getExtractedText(): ?string
    {
        return $this->extractedText;
    }

    public function setNoteContent(string $noteContent): static
    {
        $this->noteContent = $noteContent;

        return $this;
    }

    public function getNoteContent(): ?string
    {
        return $this->noteContent;
    }

    public function isKeepForever(): bool
    {
        return $this->keepForever;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getTrashedAt(): ?DateTimeImmutable
    {
        return $this->trashedAt;
    }

    public function isTrashed(): bool
    {
        return null !== $this->trashedAt;
    }

    public function trash(DateTimeImmutable $now): static
    {
        $this->trashedAt = $now;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
