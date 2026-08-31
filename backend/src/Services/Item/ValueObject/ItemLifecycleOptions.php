<?php

declare(strict_types = 1);

namespace App\Services\Item\ValueObject;

use App\Enum\TtlPreset;
use DateTimeImmutable;

/**
 * The TTL/naming inputs shared by every item-creation flow (file, URL,
 * photo) — pulled out once createUrl()/createPhoto() joined createFile() in
 * Part 4, rather than repeating the same four parameters three times.
 */
final readonly class ItemLifecycleOptions
{
    public function __construct(
        public ?string $name,
        public bool $keepForever,
        public ?TtlPreset $ttlPreset,
        public ?DateTimeImmutable $customExpiresAt,
    ) {}
}
