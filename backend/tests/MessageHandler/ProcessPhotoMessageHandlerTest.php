<?php

declare(strict_types = 1);

namespace App\Tests\MessageHandler;

use App\Entity\Item;
use App\Enum\ItemProcessingStatus;
use App\Enum\ItemType;
use App\Item\OcrService;
use App\Item\ThumbnailService;
use App\Message\ProcessPhotoMessage;
use App\MessageHandler\ProcessPhotoMessageHandler;
use App\Repository\ItemRepository;
use App\Storage\StorageService;
use App\Tests\SystemKernelTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

/**
 * No mocked HTTP here — unlike the URL scraper, a photo's processing (GD
 * thumbnail + tesseract OCR) is entirely local, so this runs the real thing.
 */
class ProcessPhotoMessageHandlerTest extends SystemKernelTestCase
{
    private ItemRepository $itemRepository;

    private StorageService $storageService;

    private ProcessPhotoMessageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itemRepository = self::getContainer()->get(ItemRepository::class);
        $this->storageService = self::getContainer()->get(StorageService::class);
        $this->handler = new ProcessPhotoMessageHandler(
            itemRepository: $this->itemRepository,
            storageService: $this->storageService,
            thumbnailService: self::getContainer()->get(ThumbnailService::class),
            ocrService: self::getContainer()->get(OcrService::class),
            logger: new NullLogger(),
        );
    }

    private function createPendingPhotoItem(string $imagePath): Item
    {
        $category = $this->databaseMockManager->createCategory('Photo processing test category');
        $storageKey = 'tests/photos/' . Uuid::v4() . '.png';
        $this->storageService->uploadFromPath($storageKey, $imagePath);

        $item = new Item(
            category: $category,
            type: ItemType::PHOTO,
            name: 'photo-test.png',
            keepForever: false,
            expiresAt: null,
            processingStatus: ItemProcessingStatus::PENDING,
        );
        $item->setFileData(
            originalFilename: 'photo-test.png',
            mimeType: 'image/png',
            size: (int) filesize($imagePath),
            storageKey: $storageKey,
            contentHash: hash_file('sha256', $imagePath),
        );
        $this->itemRepository->save($item);

        return $item;
    }

    private function createImageWithText(string $text): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-photo-handler-test-') . '.png';
        $image = imagecreatetruecolor(400, 120);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 20, 40, $text, imagecolorallocate($image, 0, 0, 0));
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    public function testGeneratesThumbnailAndExtractsOcrText(): void
    {
        $imagePath = $this->createImageWithText('POUCH');

        try {
            $item = $this->createPendingPhotoItem($imagePath);
        } finally {
            unlink($imagePath);
        }

        ($this->handler)(new ProcessPhotoMessage($item->getId()));

        $updated = $this->itemRepository->find($item->getId());
        self::assertNotNull($updated);
        self::assertSame(ItemProcessingStatus::COMPLETED, $updated->getProcessingStatus());
        self::assertNotNull($updated->getThumbnailStorageKey());
        self::assertTrue($this->storageService->exists($updated->getThumbnailStorageKey()));
        self::assertStringContainsStringIgnoringCase('POUCH', (string) $updated->getExtractedText());
    }

    public function testStillCompletesWhenTheImageHasNoText(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-photo-handler-blank-') . '.png';
        $image = imagecreatetruecolor(200, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 100, 150, 200));
        imagepng($image, $path);
        imagedestroy($image);

        try {
            $item = $this->createPendingPhotoItem($path);
        } finally {
            unlink($path);
        }

        ($this->handler)(new ProcessPhotoMessage($item->getId()));

        $updated = $this->itemRepository->find($item->getId());
        self::assertNotNull($updated);
        self::assertSame(ItemProcessingStatus::COMPLETED, $updated->getProcessingStatus());
        self::assertNotNull($updated->getThumbnailStorageKey());
    }
}
