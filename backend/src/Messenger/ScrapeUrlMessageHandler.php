<?php

declare(strict_types = 1);

namespace App\Messenger;

use App\Repository\ItemRepository;
use App\Services\Item\Scraper\OpenGraphScraperInterface;
use App\Services\Item\Scraper\SafeUrlFetcherInterface;
use App\Services\Item\ThumbnailServiceInterface;
use App\Services\Storage\StorageServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Throwable;

use function explode;
use function fclose;
use function fopen;
use function fwrite;
use function is_resource;
use function is_string;
use function sprintf;
use function str_starts_with;
use function strlen;
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
final readonly class ScrapeUrlMessageHandler
{
    private const string THUMBNAIL_STORAGE_PREFIX = 'thumbnails';

    // Guards against a malicious/misbehaving OG image URL streaming
    // unbounded data at the worker and filling its disk — a normal page
    // thumbnail is nowhere near this size.
    private const int MAX_THUMBNAIL_DOWNLOAD_BYTES = 15 * 1024 * 1024;

    public function __construct(
        private ItemRepository $itemRepository,
        private OpenGraphScraperInterface $scraper,
        private ThumbnailServiceInterface $thumbnailService,
        private StorageServiceInterface $storageService,
        private SafeUrlFetcherInterface $safeUrlFetcher,
        private LoggerInterface $logger,
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
     *
     * Post-review fix: this used to call httpClient->request() directly,
     * with none of the SSRF protection OpenGraphScraper's own page fetch
     * has — a page's og:image is exactly as attacker-controlled as the page
     * itself, so it needs the same treatment (SafeUrlFetcher: DNS-resolved
     * host check + IP pinning + re-validated on every redirect hop, not just
     * this first request).
     */
    private function tryDownloadThumbnail(string $imageUrl, int $itemId): ?string
    {
        $downloadedPath = null;
        $thumbnailPath = null;

        try {
            $response = $this->safeUrlFetcher->fetch($imageUrl, ['timeout' => 10]);
            $contentType = $response->getHeaders()['content-type'][0] ?? '';
            $mimeType = explode(';', $contentType)[0];

            if (false === str_starts_with($mimeType, 'image/')) {
                return null;
            }

            $declaredLength = $response->getHeaders()['content-length'][0] ?? null;
            if (null !== $declaredLength && self::MAX_THUMBNAIL_DOWNLOAD_BYTES < (int) $declaredLength) {
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

            $downloadedBytes = 0;

            foreach ($this->safeUrlFetcher->stream($response, 10) as $chunk) {
                $content = $chunk->getContent();
                $downloadedBytes += strlen($content);

                // A server can lie about (or omit) Content-Length, so the
                // actual byte count read is the real guard, not just the
                // declared-length check above.
                if (self::MAX_THUMBNAIL_DOWNLOAD_BYTES < $downloadedBytes) {
                    fclose($output);

                    return null;
                }

                fwrite($output, $content);
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
