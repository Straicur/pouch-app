<?php

declare(strict_types = 1);

namespace App\Messenger;

use App\Repository\ItemRepository;
use App\Services\Item\OcrServiceInterface;
use App\Services\Item\ThumbnailServiceInterface;
use App\Services\Storage\StorageServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Throwable;

use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[AsMessageHandler]
final readonly class ProcessPhotoMessageHandler
{
    private const string THUMBNAIL_STORAGE_PREFIX = 'thumbnails';

    public function __construct(
        private ItemRepository $itemRepository,
        private StorageServiceInterface $storageService,
        private ThumbnailServiceInterface $thumbnailService,
        private OcrServiceInterface $ocrService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ProcessPhotoMessage $message): void
    {
        $item = $this->itemRepository->find($message->itemId);
        if (null === $item || $item->isTrashed()) {
            return;
        }

        $storageKey = $item->getStorageKey();
        $mimeType = $item->getMimeType();
        if (null === $storageKey || null === $mimeType) {
            // Shouldn't happen for a PHOTO item — defensive, not a retryable failure.
            $this->logger->error(sprintf('Item #%d: missing storage key/mime type, cannot process photo', $item->getId()));

            return;
        }

        $localPath = tempnam(sys_get_temp_dir(), 'pouch-photo-');
        if (false === $localPath) {
            $this->logger->error(sprintf('Item #%d: could not create a temp file', $item->getId()));

            return;
        }

        $thumbnailPath = null;

        try {
            $this->storageService->downloadToPath($storageKey, $localPath);

            $thumbnailPath = $this->thumbnailService->generate($localPath, $mimeType);
            $thumbnailKey = sprintf('%s/%s.jpg', self::THUMBNAIL_STORAGE_PREFIX, Uuid::v4()->toRfc4122());
            $this->storageService->uploadFromPath($thumbnailKey, $thumbnailPath);
            $item->setThumbnailStorageKey($thumbnailKey);

            $text = $this->ocrService->extractText($localPath);
            $item->setExtractedText('' !== $text ? $text : null);

            $item->markCompleted();
        } catch (Throwable $exception) {
            $this->logger->error(sprintf('Item #%d: photo processing failed: %s', $item->getId(), $exception->getMessage()));
            $item->markFailed($exception->getMessage());
        } finally {
            @unlink($localPath);
            if (null !== $thumbnailPath) {
                @unlink($thumbnailPath);
            }
        }

        $this->itemRepository->save($item);
    }
}
