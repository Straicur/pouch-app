<?php

declare(strict_types = 1);

namespace App\Tests\Item\Scraper;

use App\Item\Scraper\OpenGraphScraper;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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

    /**
     * Post-review fix: redirects used to be followed entirely inside
     * Symfony's HttpClient (`max_redirects: 5`), so a page 302-ing straight
     * at a cloud metadata endpoint or an RFC1918 address was never checked —
     * only the *input* URL ever went through UrlValidator. Now every hop
     * does. This is 169.254.169.254 specifically — the AWS/GCP/Azure
     * instance-metadata address that classic SSRF-via-redirect exploits.
     */
    public function testRejectsARedirectToAPrivateOrLinkLocalAddress(): void
    {
        $client = new MockHttpClient(
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['location' => 'http://169.254.169.254/latest/meta-data/']]),
        );
        $scraper = new OpenGraphScraper($client);

        $this->expectException(RuntimeException::class);

        $scraper->scrape('https://example.com/redirects-to-metadata');
    }

    /**
     * The safe counterpart of the above — a redirect to another ordinary
     * public host must still work, since the fix is "validate every hop",
     * not "never follow a redirect".
     */
    public function testFollowsARedirectToAnotherPublicHost(): void
    {
        $html = '<html><head><title>Landed</title></head><body><p>text</p></body></html>';

        $client = new MockHttpClient(function (string $method, string $url) use ($html) {
            if (str_contains($url, '/start')) {
                return new MockResponse('', ['http_code' => 302, 'response_headers' => ['location' => 'https://example.org/landed']]);
            }

            return new MockResponse($html, ['response_headers' => ['content-type' => 'text/html']]);
        });
        $scraper = new OpenGraphScraper($client);

        $scraped = $scraper->scrape('https://example.com/start');

        self::assertSame('Landed', $scraped->title);
    }
}
