<?php

declare(strict_types = 1);

namespace App\MessageHandler;

use App\Item\Scraper\OpenGraphScraperInterface;
use App\Item\ThumbnailServiceInterface;
use App\Message\ScrapeUrlMessage;
use App\Repository\ItemRepository;
use App\Storage\StorageServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function explode;
use function fclose;
use function fopen;
use function fwrite;
use function is_resource;
use function is_string;
use function sprintf;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * The roadmap's "one handler as a pattern" for Messenger — scrapes OpenGraph
 * metadata + a text snapshot for a URL item, and best-effort downloads/
 * resizes the OG image as the item's thumbnail. Never blocks the request
 * that created the item (see ItemService::createUrl()).
 */
#[AsMessageHandler]
final class ScrapeUrlMessageHandler
{
    private const string THUMBNAIL_STORAGE_PREFIX = 'thumbnails';

    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly OpenGraphScraperInterface $scraper,
        private readonly ThumbnailServiceInterface $thumbnailService,
        private readonly StorageServiceInterface $storageService,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ScrapeUrlMessage $message): void
    {
        $item = $this->itemRepository->find($message->itemId);
        // Deleted/trashed (or somehow missing) before we got to it — nothing to do.
        if (null === $item || $item->isTrashed() || null === $item->getUrl()) {
            return;
        }

        try {
            $scraped = $this->scraper->scrape($item->getUrl());
        } catch (Throwable $exception) {
            $this->logger->error(sprintf('Item #%d: URL scrape failed: %s', $item->getId(), $exception->getMessage()));
            $item->markFailed($exception->getMessage());
            $this->itemRepository->save($item);

            return;
        }

        $item->setPageMetadata($scraped->title, $scraped->description);
        $item->setExtractedText($scraped->text);

        if (null !== $scraped->imageUrl) {
            $thumbnailKey = $this->tryDownloadThumbnail($scraped->imageUrl, $item->getId());
            if (null !== $thumbnailKey) {
                $item->setThumbnailStorageKey($thumbnailKey);
            }
        }

        $item->markCompleted();
        $this->itemRepository->save($item);
    }

    /**
     * A missing/unfetchable/non-image OG thumbnail isn't a scrape failure —
     * title/description/text are still useful without it.
     */
    private function tryDownloadThumbnail(string $imageUrl, int $itemId): ?string
    {
        $downloadedPath = null;
        $thumbnailPath = null;

        try {
            $response = $this->httpClient->request('GET', $imageUrl, ['timeout' => 10]);
            $contentType = $response->getHeaders()['content-type'][0] ?? '';
            $mimeType = explode(';', $contentType)[0];

            if (false === str_starts_with($mimeType, 'image/')) {
                return null;
            }

            $downloadedPath = tempnam(sys_get_temp_dir(), 'pouch-og-image-');
            if (false === $downloadedPath) {
                return null;
            }

            $output = fopen($downloadedPath, 'wb');
            if (false === is_resource($output)) {
                return null;
            }

            foreach ($this->httpClient->stream($response, 10) as $chunk) {
                fwrite($output, $chunk->getContent());
            }
            fclose($output);

            $thumbnailPath = $this->thumbnailService->generate($downloadedPath, $mimeType);

            $key = sprintf('%s/%s.jpg', self::THUMBNAIL_STORAGE_PREFIX, Uuid::v4()->toRfc4122());
            $this->storageService->uploadFromPath($key, $thumbnailPath);

            return $key;
        } catch (Throwable $exception) {
            $this->logger->info(sprintf('Item #%d: OG thumbnail skipped: %s', $itemId, $exception->getMessage()));

            return null;
        } finally {
            // is_string(), not null-check: phpstan conservatively still sees
            // tempnam()'s string|false return type here (a thrown exception
            // could in theory land control in `finally` before the false-check
            // above narrows it).
            if (is_string($downloadedPath)) {
                @unlink($downloadedPath);
            }
            if (is_string($thumbnailPath)) {
                @unlink($thumbnailPath);
            }
        }
    }
}
