<?php

declare(strict_types = 1);

namespace App\Item\Scraper;

final readonly class ScrapedPage
{
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?string $imageUrl,
        public ?string $text,
    ) {}
}
