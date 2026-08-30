<?php

declare(strict_types = 1);

namespace App\Message;

/**
 * Dispatched right after a photo item is uploaded — see
 * ProcessPhotoMessageHandler (thumbnail + OCR).
 */
final readonly class ProcessPhotoMessage
{
    public function __construct(
        public int $itemId,
    ) {}
}
