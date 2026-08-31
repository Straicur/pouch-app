<?php

declare(strict_types = 1);

namespace App\Services\Item\Collector;

use App\Services\Audit\AuditLoggerInterface;
use App\Entity\GcRunLog;
use App\Entity\ItemVersion;
use App\Exception\StorageException;
use App\Repository\GcRunLogRepository;
use App\Repository\ItemRepository;
use App\Repository\ItemVersionRepository;
use App\Services\Storage\StorageServiceInterface;
use DateInterval;
use DateTimeImmutable;
use Override;
use Psr\Log\LoggerInterface;

use function array_map;
use function count;
use function sprintf;

class ItemGarbageCollector implements ItemGarbageCollectorInterface
{
    // Product doc: items stay in the trash for 7 days before being purged for good.
    private const string DEFAULT_RETENTION = 'P7D';

    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly ItemVersionRepository $itemVersionRepository,
        private readonly GcRunLogRepository $gcRunLogRepository,
        private readonly StorageServiceInterface $storageService,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly LoggerInterface $logger,
    ) {}

    #[Override]
    public function run(string $trigger, ?DateTimeImmutable $now = null, ?DateInterval $retention = null): GcRunLog
    {
        $expiredCount = $this->expireOverdueItems($now);
        $purgedCount = $this->purgeTrash($now, $retention);

        $runLog = new GcRunLog(trigger: $trigger, expiredCount: $expiredCount, purgedCount: $purgedCount);
        $this->gcRunLogRepository->save($runLog);

        return $runLog;
    }

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

            // Part 8: every archived version (see ItemVersion) has its own
            // storage object too, kept around precisely so the version stays
            // downloadable — none of that has anywhere else to go once the
            // item itself is gone, so it's purged right alongside it.
            $versionStorageKeys = array_map(
                static fn (ItemVersion $version): string => $version->getStorageKey(),
                $this->itemVersionRepository->findByItemOrderedByVersion($item),
            );

            try {
                // Not every type has both (URL items have no primary file,
                // FILE items have no thumbnail) — delete whichever exist.
                foreach ([$item->getStorageKey(), $item->getThumbnailStorageKey(), ...$versionStorageKeys] as $storageKey) {
                    if (null !== $storageKey) {
                        $this->storageService->delete($storageKey);
                    }
                }
            } catch (StorageException $exception) {
                // Leave the DB row in the trash so the next run retries the storage
                // delete too — better a lingering trash row than an orphaned blob
                // with nothing left pointing at it.
                $this->logger->error(sprintf('Item #%d: failed to delete storage object, will retry next run: %s', $itemId, $exception->getMessage()));

                continue;
            }

            $this->itemRepository->remove($item);
            $this->logger->info(sprintf('Item #%d: purged from trash', $itemId));
            // No $request (this runs from a cron/console command as often as
            // from an admin's manual trigger) and no $user (system-triggered,
            // not any one person's action) — see AuditLoggerInterface.
            $this->auditLogger->log(AuditLoggerInterface::ACTION_PURGE, AuditLoggerInterface::RESOURCE_ITEM, $itemId, null, null);
            ++$purgedCount;
        }

        return $purgedCount;
    }
}
