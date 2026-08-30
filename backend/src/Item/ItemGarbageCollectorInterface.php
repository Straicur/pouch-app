<?php

declare(strict_types = 1);

namespace App\Item;

use DateInterval;
use DateTimeImmutable;

/**
 * Backs the `app:item:gc` console command (and, later, the admin dashboard's
 * "Run GC Now" from Part 10) — two independent phases run back to back:
 * expired TTLs move to the trash, then anything that's been in the trash long
 * enough is purged for good (DB row + storage object).
 */
interface ItemGarbageCollectorInterface
{
    /**
     * @return int how many items were moved to the trash
     */
    public function expireOverdueItems(?DateTimeImmutable $now = null): int;

    /**
     * @return int how many items were permanently deleted
     */
    public function purgeTrash(?DateTimeImmutable $now = null, ?DateInterval $retention = null): int;
}
