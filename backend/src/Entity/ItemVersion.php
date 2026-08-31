<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Repository\ItemVersionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Part 8: a snapshot of a FILE item's primary asset *before* it got
 * overwritten — Item itself always holds the current version (same id/URL,
 * per the product doc's "bez zmiany ID/adresu w drzewie"), this table is
 * purely the history behind it. $version is 1-based and increases by one
 * each time the item is overwritten (see ItemService::overwriteFile()); the
 * item's own current file is implicitly "the next version after the last row
 * here" and isn't duplicated into this table.
 */
#[ORM\Entity(repositoryClass: ItemVersionRepository::class)]
#[ORM\Table(name: 'item_version')]
#[ORM\UniqueConstraint(name: 'uniq_item_version', columns: ['item_id', 'version'])]
class ItemVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'item_version_id', type: Types::INTEGER, unique: true, nullable: false, options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'item_id', nullable: false, onDelete: 'CASCADE')]
    private Item $item;

    #[ORM\Column(name: 'version', type: Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    private int $version;

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

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private DateTimeImmutable $createdAt;

    public function __construct(
        Item $item,
        int $version,
        string $originalFilename,
        string $mimeType,
        int $size,
        string $storageKey,
        string $contentHash,
    ) {
        $this->item = $item;
        $this->version = $version;
        $this->originalFilename = $originalFilename;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->storageKey = $storageKey;
        $this->contentHash = $contentHash;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getVersion(): int
    {
        return $this->version;
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
