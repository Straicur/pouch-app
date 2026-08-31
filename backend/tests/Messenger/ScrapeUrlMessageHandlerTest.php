<?php

declare(strict_types = 1);

namespace App\Tests\Messenger;

use App\Entity\Item;
use App\Enum\ItemProcessingStatus;
use App\Enum\ItemType;
use App\Services\Item\Scraper\OpenGraphScraper;
use App\Services\Item\Scraper\SafeUrlFetcher;
use App\Services\Item\ThumbnailService;
use App\Messenger\ScrapeUrlMessage;
use App\Messenger\ScrapeUrlMessageHandler;
use App\Repository\ItemRepository;
use App\Services\Storage\StorageService;
use App\Tests\SystemKernelTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ScrapeUrlMessageHandlerTest extends SystemKernelTestCase
{
    private ItemRepository $itemRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itemRepository = self::getContainer()->get(ItemRepository::class);
    }

    private function createPendingUrlItem(string $url): Item
    {
        $category = $this->databaseMockManager->createCategory('Scrape test category');

        $item = new Item(
            category: $category,
            type: ItemType::URL,
            name: $url,
            keepForever: false,
            expiresAt: null,
            processingStatus: ItemProcessingStatus::PENDING,
        );
        $item->setUrl($url);
        $this->itemRepository->save($item);

        return $item;
    }

    private function tinyJpegBytes(): string
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return (string) $bytes;
    }

    private function handler(HttpClientInterface $mockClient): ScrapeUrlMessageHandler
    {
        return new ScrapeUrlMessageHandler(
            itemRepository: $this->itemRepository,
            scraper: new OpenGraphScraper(new SafeUrlFetcher($mockClient)),
            thumbnailService: self::getContainer()->get(ThumbnailService::class),
            storageService: self::getContainer()->get(StorageService::class),
            safeUrlFetcher: new SafeUrlFetcher($mockClient),
            logger: new NullLogger(),
        );
    }

    public function testScrapesTitleDescriptionTextAndThumbnail(): void
    {
        $item = $this->createPendingUrlItem('https://example.com/article');

        // The OG image is on the same (real, always-resolvable) host as the
        // page itself — post-review fix, the thumbnail download now goes
        // through SafeUrlFetcher too, which DNS-resolves whatever host it's
        // given (see that class/UrlValidator), same as the page fetch
        // always did; a made-up subdomain wouldn't actually resolve.
        $html = <<<'HTML'
            <html><head>
                <meta property="og:title" content="Great Article">
                <meta property="og:description" content="A great description">
                <meta property="og:image" content="https://example.com/thumb.jpg">
            </head><body><script>ignored()</script><p>Real page text goes here.</p></body></html>
            HTML;

        $mockClient = new MockHttpClient(function (string $method, string $url) use ($html) {
            if (str_contains($url, 'thumb.jpg')) {
                return new MockResponse($this->tinyJpegBytes(), ['response_headers' => ['content-type' => 'image/jpeg']]);
            }

            return new MockResponse($html, ['response_headers' => ['content-type' => 'text/html']]);
        });

        $this->handler($mockClient)(new ScrapeUrlMessage($item->getId()));

        $updated = $this->itemRepository->find($item->getId());
        self::assertNotNull($updated);
        self::assertSame(ItemProcessingStatus::COMPLETED, $updated->getProcessingStatus());
        self::assertSame('Great Article', $updated->getPageTitle());
        self::assertSame('A great description', $updated->getPageDescription());
        self::assertStringContainsString('Real page text goes here.', (string) $updated->getExtractedText());
        self::assertNotNull($updated->getThumbnailStorageKey());

        $storageService = self::getContainer()->get(StorageService::class);
        self::assertTrue($storageService->exists($updated->getThumbnailStorageKey()));
    }

    public function testFetchFailureMarksItemFailedWithoutThrowing(): void
    {
        $item = $this->createPendingUrlItem('https://example.com/unreachable');

        $mockClient = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['http_code' => 500]));

        $this->handler($mockClient)(new ScrapeUrlMessage($item->getId()));

        $updated = $this->itemRepository->find($item->getId());
        self::assertNotNull($updated);
        self::assertSame(ItemProcessingStatus::FAILED, $updated->getProcessingStatus());
        self::assertNotNull($updated->getProcessingError());
    }

    public function testMissingOgImageStillCompletesWithoutThumbnail(): void
    {
        $item = $this->createPendingUrlItem('https://example.com/no-image');

        $html = '<html><head><meta property="og:title" content="No Image Here"></head><body><p>text</p></body></html>';
        $mockClient = new MockHttpClient(new MockResponse($html, ['response_headers' => ['content-type' => 'text/html']]));

        $this->handler($mockClient)(new ScrapeUrlMessage($item->getId()));

        $updated = $this->itemRepository->find($item->getId());
        self::assertNotNull($updated);
        self::assertSame(ItemProcessingStatus::COMPLETED, $updated->getProcessingStatus());
        self::assertNull($updated->getThumbnailStorageKey());
    }

    /**
     * Post-review fix regression: the OG image download used to call
     * httpClient->request() directly, with no host-safety check at all — a
     * page under attacker control (or simply compromised) could point
     * og:image straight at 169.254.169.254 and the thumbnail fetch would
     * have gone through unchecked. It's now routed through the same
     * SafeUrlFetcher as the page itself.
     */
    public function testOgImagePointingAtAPrivateAddressIsSkippedNotFetched(): void
    {
        $item = $this->createPendingUrlItem('https://example.com/malicious-og-image');

        $html = <<<'HTML'
            <html><head>
                <meta property="og:title" content="Looks Normal">
                <meta property="og:image" content="http://169.254.169.254/latest/meta-data/">
            </head><body><p>text</p></body></html>
            HTML;

        $mockClient = new MockHttpClient(new MockResponse($html, ['response_headers' => ['content-type' => 'text/html']]));

        $this->handler($mockClient)(new ScrapeUrlMessage($item->getId()));

        $updated = $this->itemRepository->find($item->getId());
        self::assertNotNull($updated);
        // The page itself scraped fine — only the (unsafe) thumbnail is skipped.
        self::assertSame(ItemProcessingStatus::COMPLETED, $updated->getProcessingStatus());
        self::assertSame('Looks Normal', $updated->getPageTitle());
        self::assertNull($updated->getThumbnailStorageKey());
    }
}
