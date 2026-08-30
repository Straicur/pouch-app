<?php

declare(strict_types = 1);

namespace App\Enum;

/**
 * Only URL and photo items go through PENDING — they have an async step
 * (scraping/thumbnail+OCR). File items are COMPLETED the moment they're
 * created since there's nothing left to do.
 */
enum ItemProcessingStatus: string
{
    case COMPLETED = 'completed';

    case PENDING = 'pending';

    case FAILED = 'failed';
}
