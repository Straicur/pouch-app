<?php

declare(strict_types = 1);

namespace App\Services\Item\Scraper;

use RuntimeException;

interface OpenGraphScraperInterface
{
    /**
     * @throws RuntimeException if the page can't be fetched at all
     */
    public function scrape(string $url): ScrapedPage;
}
