<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Enum\ItemType;
use App\Repository\ItemRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One entity for every item type (see ItemType) — Part 3 only implements the
 * "general file" behaviour (App\Item\FileItemService), but the schema doesn't
 * hardcode that it's the only one.
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

    #[ORM\Column(name: 'original_filename', type: Types::STRING, length: 255, nullable: false)]
    private string $originalFilename;

    #[ORM\Column(name: 'mime_type', type: Types::STRING, length: 255, nullable: false)]
    private string $mimeType;

    #[ORM\Column(name: 'size', type: Types::BIGINT, nullable: false, options: ['unsigned' => true])]
    private int $size;

    #[ORM\Column(name: 'storage_key', type: Types::STRING, length: 512, unique: true, nullable: false)]
    private string $storageKey;

    #[ORM\Column(name: 'content_hash', type: Types::STRING, length: 64, nullable: false)]
    private string $contentHash;

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
        string $originalFilename,
        string $mimeType,
        int $size,
        string $storageKey,
        string $contentHash,
        bool $keepForever,
        ?DateTimeImmutable $expiresAt,
    ) {
        $this->category = $category;
        $this->type = $type;
        $this->name = $name;
        $this->originalFilename = $originalFilename;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->storageKey = $storageKey;
        $this->contentHash = $contentHash;
        $this->keepForever = $keepForever;
        $this->expiresAt = $expiresAt;
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

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
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
