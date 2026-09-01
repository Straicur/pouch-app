<?php

declare(strict_types = 1);

namespace App\Services\Item\Collector;

use App\Entity\GcRunLog;
use DateInterval;
use DateTimeImmutable;

/**
 * Backs the `app:item:gc` console command and the admin dashboard's "Run GC
 * Now" (Part 10) — both go through run() below, which is what actually
 * records a GcRunLog row; expireOverdueItems()/purgeTrash() stay public
 * (and independently callable) mainly because the tests already exercise
 * them that way, and purgeTrash() alone is a reasonable thing to want.
 */
interface ItemGarbageCollectorInterface
{
    /**
     * $pouchId scopes both phases to one pouch when given — the real
     * `app:item:gc` cron never passes one (a full sweep, every pouch); an
     * admin's "Run GC Now" may, for a per-pouch run.
     *
     * @return int how many items were moved to the trash
     */
    public function expireOverdueItems(?DateTimeImmutable $now = null, ?int $pouchId = null): int;

    /**
     * @return int how many items were permanently deleted
     */
    public function purgeTrash(?DateTimeImmutable $now = null, ?DateInterval $retention = null, ?int $pouchId = null): int;

    /**
     * Runs both phases back to back and records the outcome as a GcRunLog
     * row — $trigger is GcRunLog::TRIGGER_CRON (the real `app:item:gc`
     * invocation) or GcRunLog::TRIGGER_MANUAL (an admin's "Run GC Now").
     */
    public function run(string $trigger, ?DateTimeImmutable $now = null, ?DateInterval $retention = null, ?int $pouchId = null): GcRunLog;
}
