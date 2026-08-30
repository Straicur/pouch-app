<?php

declare(strict_types = 1);

namespace App\Tests\Item\Scraper;

use App\Item\Scraper\OpenGraphScraper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenGraphScraperTest extends TestCase
{
    public function testExtractsOpenGraphTitleDescriptionImageAndVisibleText(): void
    {
        $html = <<<'HTML'
            <html>
            <head>
                <title>Fallback title (should be ignored)</title>
                <meta property="og:title" content="Great Article">
                <meta property="og:description" content="A great description">
                <meta property="og:image" content="https://cdn.example.com/thumb.jpg">
            </head>
            <body>
                <script>console.log("should not appear in text");</script>
                <style>.foo { color: red; }</style>
                <p>This is the real, visible page content.</p>
            </body>
            </html>
            HTML;

        $client = new MockHttpClient(new MockResponse($html, ['response_headers' => ['content-type' => 'text/html']]));
        $scraper = new OpenGraphScraper($client);

        $scraped = $scraper->scrape('https://example.com/article');

        self::assertSame('Great Article', $scraped->title);
        self::assertSame('A great description', $scraped->description);
        self::assertSame('https://cdn.example.com/thumb.jpg', $scraped->imageUrl);
        self::assertStringContainsString('This is the real, visible page content.', (string) $scraped->text);
        self::assertStringNotContainsString('should not appear', (string) $scraped->text);
        self::assertStringNotContainsString('color: red', (string) $scraped->text);
    }

    public function testFallsBackToTitleTagWhenNoOpenGraphTitle(): void
    {
        $html = '<html><head><title>Plain Title</title></head><body><p>Text</p></body></html>';
        $client = new MockHttpClient(new MockResponse($html, ['response_headers' => ['content-type' => 'text/html']]));
        $scraper = new OpenGraphScraper($client);

        $scraped = $scraper->scrape('https://example.com/');

        self::assertSame('Plain Title', $scraped->title);
        self::assertNull($scraped->imageUrl);
    }

    public function testResolvesRelativeImageUrlAgainstThePageOrigin(): void
    {
        $html = '<html><head><meta property="og:image" content="/img/cover.png"></head><body>text</body></html>';
        $client = new MockHttpClient(new MockResponse($html, ['response_headers' => ['content-type' => 'text/html']]));
        $scraper = new OpenGraphScraper($client);

        $scraped = $scraper->scrape('https://example.com/some/page');

        self::assertSame('https://example.com/img/cover.png', $scraped->imageUrl);
    }
}
