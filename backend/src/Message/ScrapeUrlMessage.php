<?php

declare(strict_types = 1);

namespace App\Message;

/**
 * Dispatched right after a URL item is created — see ScrapeUrlMessageHandler
 * for what happens with it. Carries only the id (not the URL itself) so the
 * handler always reads the current, authoritative state from the DB.
 */
final readonly class ScrapeUrlMessage
{
    public function __construct(
        public int $itemId,
    ) {}
}
