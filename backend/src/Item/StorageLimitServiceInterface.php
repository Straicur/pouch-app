<?php

declare(strict_types = 1);

namespace App\Item;

use App\Enum\ItemType;

/**
 * Part 10: "globalne limity wagowe per typ" — an admin-set override of
 * FileValidator/ImageValidator's built-in defaults. Only FILE and PHOTO have
 * a meaningful size limit at all (URL/NOTE items have no uploaded bytes).
 */
interface StorageLimitServiceInterface
{
    public function getMaxSizeBytes(ItemType $type): int;

    public function setMaxSizeBytes(ItemType $type, int $maxSizeBytes): void;

    /**
     * @return array<string, int> every FILE/PHOTO limit (override or
     *                            default), keyed by ItemType value
     */
    public function getAllMaxSizeBytes(): array;
}
