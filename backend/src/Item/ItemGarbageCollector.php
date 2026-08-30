<?php

declare(strict_types = 1);

namespace App\Item;

use App\Exception\StorageException;
use App\Repository\ItemRepository;
use App\Storage\StorageServiceInterface;
use DateInterval;
use DateTimeImmutable;
use Override;
use Psr\Log\LoggerInterface;

use function count;
use function sprintf;

class ItemGarbageCollector implements ItemGarbageCollectorInterface
{
    // Product doc: items stay in the trash for 7 days before being purged for good.
    private const string DEFAULT_RETENTION = 'P7D';

    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly StorageServiceInterface $storageService,
        private readonly LoggerInterface $logger,
    ) {}

    #[Override]
    public function expireOverdueItems(?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable();

        $overdueItems = $this->itemRepository->findOverdueForTrash($now);

        foreach ($overdueItems as $item) {
            $item->trash($now);
            $this->itemRepository->save($item);
            $this->logger->info(sprintf('Item #%d: TTL expired, moved to trash', $item->getId()));
        }

        return count($overdueItems);
    }

    #[Override]
    public function purgeTrash(?DateTimeImmutable $now = null, ?DateInterval $retention = null): int
    {
        $now ??= new DateTimeImmutable();
        $retention ??= new DateInterval(self::DEFAULT_RETENTION);

        $trashedBefore = $now->sub($retention);
        $overdueItems = $this->itemRepository->findOverdueForPurge($trashedBefore);

        $purgedCount = 0;

        foreach ($overdueItems as $item) {
            // Captured up front: EntityManager::remove() + flush() below leaves
            // $item's identifier inaccessible on the now-removed instance.
            $itemId = $item->getId();

            try {
                $this->storageService->delete($item->getStorageKey());
            } catch (StorageException $exception) {
                // Leave the DB row in the trash so the next run retries the storage
                // delete too — better a lingering trash row than an orphaned blob
                // with nothing left pointing at it.
                $this->logger->error(sprintf('Item #%d: failed to delete storage object, will retry next run: %s', $itemId, $exception->getMessage()));

                continue;
            }

            $this->itemRepository->remove($item);
            $this->logger->info(sprintf('Item #%d: purged from trash', $itemId));
            ++$purgedCount;
        }

        return $purgedCount;
    }
}
