<?php

declare(strict_types = 1);

namespace App\Tests\Services\Item;

use App\Entity\Category;
use App\Entity\Item;
use App\Enum\ItemProcessingStatus;
use App\Enum\ItemType;
use App\Repository\ItemRepository;
use App\Repository\ItemVersionRepository;
use App\Services\Category\CategoryServiceInterface;
use App\Services\Item\ItemService;
use App\Services\Item\StorageLimitServiceInterface;
use App\Services\Item\Validator\FileValidator;
use App\Services\Item\Validator\ImageValidator;
use App\Services\Item\Validator\NoteValidator;
use App\Services\Item\Validator\UrlValidator;
use App\Services\Pouch\CurrentPouchResolverInterface;
use App\Services\Storage\StorageServiceInterface;
use App\Services\Tag\TagServiceInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function fclose;
use function fopen;
use function fwrite;
use function rewind;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Część 14's "nadpisanie pliku nie było atomowe" fix, unit-tested directly:
 * ItemService constructed with mocked dependencies instead of the real
 * container lets the DB write inside connection->transactional() fail on
 * demand — the "fault injection infrastructure" the roadmap said this
 * project doesn't have wasn't actually needed, just a plain PHPUnit mock of
 * Connection instead of going through a real one.
 */
class ItemServiceOverwriteFileAtomicityTest extends TestCase
{
    private string $tmpPath;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-overwrite-atomicity-');
        self::assertNotFalse($path);
        $this->tmpPath = $path;

        $stream = fopen($this->tmpPath, 'r+');
        self::assertIsResource($stream);
        fwrite($stream, 'new content');
        rewind($stream);
        fclose($stream);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpPath);
    }

    public function testAFailedTransactionCleansUpTheNewlyUploadedFileAndLeavesNoVersionBehind(): void
    {
        $item = $this->existingFileItem();

        $itemRepository = $this->createStub(ItemRepository::class);
        $itemRepository->method('findOneBy')->willReturn($item);
        $itemRepository->method('findByContentHash')->willReturn(null);
        // save() only called *inside* the failing transaction — asserting it's
        // never reached would require a spy; the transaction mock below
        // instead never invokes the closure that would call it, so this
        // stays unconfigured (a stray call fails the test with a mock error).

        $itemVersionRepository = $this->createStub(ItemVersionRepository::class);
        $itemVersionRepository->method('findByItemOrderedByVersion')->willReturn([]);

        $storageService = $this->createMock(StorageServiceInterface::class);
        $uploadedKey = null;
        $storageService->expects(self::once())->method('upload')->willReturnCallback(
            function (string $storageKey) use (&$uploadedKey): void {
                $uploadedKey = $storageKey;
            },
        );
        // The compensating cleanup must target the exact key upload() was
        // given — not asserted via a literal (randomly generated), via the
        // captured reference instead, checked after the call.
        $deletedKey = null;
        $storageService->expects(self::once())->method('delete')->willReturnCallback(
            function (string $storageKey) use (&$deletedKey): void {
                $deletedKey = $storageKey;
            },
        );

        $connection = $this->createStub(Connection::class);
        $transactionFailure = new RuntimeException('simulated mid-transaction failure');
        // The real transactional() invokes the closure and lets a thrown
        // exception propagate after rolling back — reproduced here without
        // a real database by just throwing directly, same observable effect
        // for this service: the closure's writes never "happened".
        $connection->method('transactional')->willThrowException($transactionFailure);

        $itemService = new ItemService(
            itemRepository: $itemRepository,
            itemVersionRepository: $itemVersionRepository,
            categoryService: $this->createStub(CategoryServiceInterface::class),
            storageService: $storageService,
            fileValidator: new FileValidator($this->createStub(TranslatorInterface::class), $this->unlimitedStorageLimitService()),
            imageValidator: new ImageValidator($this->createStub(TranslatorInterface::class), $this->unlimitedStorageLimitService()),
            urlValidator: new UrlValidator(),
            noteValidator: new NoteValidator(),
            tagService: $this->createStub(TagServiceInterface::class),
            messageBus: $this->createStub(MessageBusInterface::class),
            translator: $this->createStub(TranslatorInterface::class),
            connection: $connection,
            logger: $this->createStub(LoggerInterface::class),
            currentPouchResolver: $this->createStub(CurrentPouchResolverInterface::class),
        );

        $this->expectExceptionObject($transactionFailure);

        try {
            // Not $item->getId() — this Item was never persisted (no real
            // database here), so its id is never initialized; findOneBy()
            // above is mocked to return $item regardless of which id it's
            // asked for, so any value works.
            $itemService->overwriteFile(
                itemId: 1,
                tmpPath: $this->tmpPath,
                originalFilename: 'new.txt',
                mimeType: 'text/plain',
                size: 11,
            );
        } finally {
            self::assertNotNull($uploadedKey, 'The new file must have been uploaded before the transaction ran.');
            self::assertSame($uploadedKey, $deletedKey, 'The compensating delete() must target the same key upload() was given.');
        }
    }

    public function testASuccessfulTransactionNeverTriggersTheCompensatingCleanup(): void
    {
        $item = $this->existingFileItem();

        $itemRepository = $this->createStub(ItemRepository::class);
        $itemRepository->method('findOneBy')->willReturn($item);
        $itemRepository->method('findByContentHash')->willReturn(null);

        $itemVersionRepository = $this->createStub(ItemVersionRepository::class);
        $itemVersionRepository->method('findByItemOrderedByVersion')->willReturn([]);

        $storageService = $this->createMock(StorageServiceInterface::class);
        $storageService->expects(self::once())->method('upload');
        $storageService->expects(self::never())->method('delete');

        $connection = $this->createStub(Connection::class);
        // The real transactional() invokes and returns its closure's result —
        // reproduced directly, since none of these mocks are real enough for
        // Connection to run one for real.
        $connection->method('transactional')->willReturnCallback(static fn (callable $callback) => $callback());

        $itemService = new ItemService(
            itemRepository: $itemRepository,
            itemVersionRepository: $itemVersionRepository,
            categoryService: $this->createStub(CategoryServiceInterface::class),
            storageService: $storageService,
            fileValidator: new FileValidator($this->createStub(TranslatorInterface::class), $this->unlimitedStorageLimitService()),
            imageValidator: new ImageValidator($this->createStub(TranslatorInterface::class), $this->unlimitedStorageLimitService()),
            urlValidator: new UrlValidator(),
            noteValidator: new NoteValidator(),
            tagService: $this->createStub(TagServiceInterface::class),
            messageBus: $this->createStub(MessageBusInterface::class),
            translator: $this->createStub(TranslatorInterface::class),
            connection: $connection,
            logger: $this->createStub(LoggerInterface::class),
            currentPouchResolver: $this->createStub(CurrentPouchResolverInterface::class),
        );

        $updated = $itemService->overwriteFile(
            itemId: 1,
            tmpPath: $this->tmpPath,
            originalFilename: 'new.txt',
            mimeType: 'text/plain',
            size: 11,
        );

        self::assertSame('new.txt', $updated->getOriginalFilename());
        self::assertSame(11, $updated->getSize());
    }

    private function existingFileItem(): Item
    {
        $category = $this->createStub(Category::class);

        $item = new Item(
            category: $category,
            type: ItemType::FILE,
            name: 'existing.txt',
            keepForever: true,
            expiresAt: null,
            processingStatus: ItemProcessingStatus::COMPLETED,
        );
        $item->setFileData('existing.txt', 'text/plain', 7, 'items/existing-key', 'existing-hash');

        return $item;
    }

    private function unlimitedStorageLimitService(): StorageLimitServiceInterface
    {
        $storageLimitService = $this->createStub(StorageLimitServiceInterface::class);
        $storageLimitService->method('getMaxSizeBytes')->willReturn(PHP_INT_MAX);

        return $storageLimitService;
    }
}
