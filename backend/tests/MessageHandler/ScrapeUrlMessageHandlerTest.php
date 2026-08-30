<?php

declare(strict_types = 1);

namespace App\Tests\MessageHandler;

use App\Entity\Item;
use App\Enum\ItemProcessingStatus;
use App\Enum\ItemType;
use App\Item\Scraper\OpenGraphScraper;
use App\Item\ThumbnailService;
use App\Message\ScrapeUrlMessage;
use App\MessageHandler\ScrapeUrlMessageHandler;
use App\Repository\ItemRepository;
use App\Storage\StorageService;
use App\Tests\SystemKernelTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

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

    public function testScrapesTitleDescriptionTextAndThumbnail(): void
    {
        $item = $this->createPendingUrlItem('https://example.com/article');

        $html = <<<'HTML'
            <html><head>
                <meta property="og:title" content="Great Article">
                <meta property="og:description" content="A great description">
                <meta property="og:image" content="https://cdn.example.com/thumb.jpg">
            </head><body><script>ignored()</script><p>Real page text goes here.</p></body></html>
            HTML;

        $mockClient = new MockHttpClient(function (string $method, string $url) use ($html) {
            if (str_contains($url, 'thumb.jpg')) {
                return new MockResponse($this->tinyJpegBytes(), ['response_headers' => ['content-type' => 'image/jpeg']]);
            }

            return new MockResponse($html, ['response_headers' => ['content-type' => 'text/html']]);
        });

        $handler = new ScrapeUrlMessageHandler(
            itemRepository: $this->itemRepository,
            scraper: new OpenGraphScraper($mockClient),
            thumbnailService: self::getContainer()->get(ThumbnailService::class),
            storageService: self::getContainer()->get(StorageService::class),
            httpClient: $mockClient,
            logger: new NullLogger(),
        );

        $handler(new ScrapeUrlMessage($item->getId()));

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

        $handler = new ScrapeUrlMessageHandler(
            itemRepository: $this->itemRepository,
            scraper: new OpenGraphScraper($mockClient),
            thumbnailService: self::getContainer()->get(ThumbnailService::class),
            storageService: self::getContainer()->get(StorageService::class),
            httpClient: $mockClient,
            logger: new NullLogger(),
        );

        $handler(new ScrapeUrlMessage($item->getId()));

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

        $handler = new ScrapeUrlMessageHandler(
            itemRepository: $this->itemRepository,
            scraper: new OpenGraphScraper($mockClient),
            thumbnailService: self::getContainer()->get(ThumbnailService::class),
            storageService: self::getContainer()->get(StorageService::class),
            httpClient: $mockClient,
            logger: new NullLogger(),
        );

        $handler(new ScrapeUrlMessage($item->getId()));

        $updated = $this->itemRepository->find($item->getId());
        self::assertNotNull($updated);
        self::assertSame(ItemProcessingStatus::COMPLETED, $updated->getProcessingStatus());
        self::assertNull($updated->getThumbnailStorageKey());
    }
}
