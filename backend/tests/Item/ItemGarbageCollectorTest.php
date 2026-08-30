<?php

declare(strict_types = 1);

namespace App\Tests\Item;

use App\Entity\Category;
use App\Entity\Item;
use App\Enum\ItemType;
use App\Item\ItemGarbageCollector;
use App\Repository\ItemRepository;
use App\Storage\StorageService;
use App\Tests\SystemKernelTestCase;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

class ItemGarbageCollectorTest extends SystemKernelTestCase
{
    private ItemRepository $itemRepository;

    private StorageService $storageService;

    private ItemGarbageCollector $itemGarbageCollector;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itemRepository = self::getContainer()->get(ItemRepository::class);
        $this->storageService = self::getContainer()->get(StorageService::class);
        $this->itemGarbageCollector = self::getContainer()->get(ItemGarbageCollector::class);
        $this->category = $this->databaseMockManager->createCategory('GC test category');
    }

    private function createStoredItem(string $content, ?DateTimeImmutable $expiresAt, bool $keepForever = false): Item
    {
        $storageKey = 'tests/gc/' . Uuid::v4();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $this->storageService->upload($storageKey, $stream);
        fclose($stream);

        $item = new Item(
            category: $this->category,
            type: ItemType::FILE,
            name: 'gc-test',
            originalFilename: 'gc-test.txt',
            mimeType: 'text/plain',
            size: strlen($content),
            storageKey: $storageKey,
            contentHash: hash('sha256', $content),
            keepForever: $keepForever,
            expiresAt: $expiresAt,
        );
        $this->itemRepository->save($item);

        return $item;
    }

    public function testExpireOverdueItemsTrashesOnlyOverdueOnes(): void
    {
        $now = new DateTimeImmutable();

        $overdue = $this->createStoredItem('overdue', $now->sub(new DateInterval('P1D')));
        $notYetDue = $this->createStoredItem('not due yet', $now->add(new DateInterval('P1D')));
        $forever = $this->createStoredItem('kept forever', null, keepForever: true);

        $count = $this->itemGarbageCollector->expireOverdueItems($now);

        self::assertSame(1, $count);
        self::assertTrue($this->itemRepository->find($overdue->getId())->isTrashed());
        self::assertFalse($this->itemRepository->find($notYetDue->getId())->isTrashed());
        self::assertFalse($this->itemRepository->find($forever->getId())->isTrashed());
    }

    public function testPurgeTrashDeletesOnlyItemsPastRetentionAndRemovesStorageObject(): void
    {
        $now = new DateTimeImmutable();
        $retention = new DateInterval('P7D');

        $longTrashed = $this->createStoredItem('long trashed', null);
        $longTrashed->trash($now->sub(new DateInterval('P8D')));
        $this->itemRepository->save($longTrashed);

        $recentlyTrashed = $this->createStoredItem('recently trashed', null);
        $recentlyTrashed->trash($now->sub(new DateInterval('P1D')));
        $this->itemRepository->save($recentlyTrashed);

        $notTrashed = $this->createStoredItem('still active', null);

        // Captured up front — purgeTrash() below removes+flushes $longTrashed,
        // after which its identifier is no longer accessible.
        $longTrashedId = $longTrashed->getId();
        $longTrashedStorageKey = $longTrashed->getStorageKey();
        $recentlyTrashedId = $recentlyTrashed->getId();
        $notTrashedId = $notTrashed->getId();

        $count = $this->itemGarbageCollector->purgeTrash($now, $retention);

        self::assertSame(1, $count);
        self::assertNull($this->itemRepository->find($longTrashedId));
        self::assertFalse($this->storageService->exists($longTrashedStorageKey));
        self::assertNotNull($this->itemRepository->find($recentlyTrashedId));
        self::assertNotNull($this->itemRepository->find($notTrashedId));
    }

    public function testFullLifecycleWithShortenedRetentionForTesting(): void
    {
        // Mirrors the manual dev test: create with a near-immediate TTL, expire it,
        // then purge with a short retention instead of waiting 7 real days.
        $now = new DateTimeImmutable();
        $item = $this->createStoredItem('lifecycle', $now->sub(new DateInterval('PT1H')));
        $itemId = $item->getId();
        $storageKey = $item->getStorageKey();

        self::assertSame(1, $this->itemGarbageCollector->expireOverdueItems($now));
        self::assertTrue($this->itemRepository->find($itemId)->isTrashed());
        self::assertTrue($this->storageService->exists($storageKey));

        $later = $now->add(new DateInterval('P1D'));
        self::assertSame(1, $this->itemGarbageCollector->purgeTrash($later, new DateInterval('P1D')));
        self::assertNull($this->itemRepository->find($itemId));
        self::assertFalse($this->storageService->exists($storageKey));
    }
}
