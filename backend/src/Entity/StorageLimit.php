<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Enum\ItemType;
use App\Repository\StorageLimitRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Part 10: "globalne limity wagowe per typ (np. max 100 MB na plik zip)" —
 * an admin-set override of FileValidator/ImageValidator's built-in defaults
 * (see StorageLimitServiceInterface). A missing row for a type just means
 * "no override yet, use the built-in default" — this table only ever holds
 * what an admin has actually changed.
 */
#[ORM\Entity(repositoryClass: StorageLimitRepository::class)]
#[ORM\Table(name: 'storage_limit')]
class StorageLimit
{
    #[ORM\Id]
    #[ORM\Column(name: 'type', type: Types::STRING, length: 32, nullable: false, enumType: ItemType::class)]
    private ItemType $type;

    #[ORM\Column(name: 'max_size_bytes', type: Types::BIGINT, nullable: false, options: ['unsigned' => true])]
    private int $maxSizeBytes;

    public function __construct(ItemType $type, int $maxSizeBytes)
    {
        $this->type = $type;
        $this->maxSizeBytes = $maxSizeBytes;
    }

    public function getType(): ItemType
    {
        return $this->type;
    }

    public function getMaxSizeBytes(): int
    {
        return $this->maxSizeBytes;
    }

    public function setMaxSizeBytes(int $maxSizeBytes): static
    {
        $this->maxSizeBytes = $maxSizeBytes;

        return $this;
    }
}
