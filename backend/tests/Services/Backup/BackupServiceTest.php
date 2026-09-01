<?php

declare(strict_types = 1);

namespace App\Tests\Services\Backup;

use App\Services\Backup\BackupService;
use App\Services\Backup\BackupServiceInterface;
use App\Services\Storage\StorageServiceInterface;
use App\Tests\SystemKernelTestCase;

use function glob;
use function is_dir;
use function sys_get_temp_dir;
use function uniqid;

use const GLOB_ONLYDIR;

/**
 * Runs the real pg_dump/pg_restore/psql binaries against the actual test
 * database and MinIO bucket — no mocking, since the whole point is proving
 * the backup/restore mechanism genuinely works end to end, not just that the
 * service calls the right methods. Slower than a typical unit test, same
 * trade-off as ItemGarbageCollectorTest.
 */
class BackupServiceTest extends SystemKernelTestCase
{
    private BackupServiceInterface $backupService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupService = self::getContainer()->get(BackupServiceInterface::class);
    }

    /**
     * The core promise of restoreTest(): a backup taken right now restores
     * cleanly into a throwaway database whose row counts match the live one.
     * Both sides are read through fresh connections (see compareRowCounts()'s
     * own comment) — this test's own SystemKernelTestCase transaction never
     * enters into it either way.
     */
    public function testRunThenRestoreTestRoundTrips(): void
    {
        $runResult = $this->backupService->run();

        self::assertTrue(is_dir($runResult->backupDir));
        self::assertGreaterThan(0, $runResult->databaseDumpBytes);
        self::assertGreaterThanOrEqual(0, $runResult->storageFileCount);

        $restoreResult = $this->backupService->restoreTest();

        self::assertTrue($restoreResult->ok, $restoreResult->detail);
    }

    public function testRunPrunesBackupsPastTheRetentionCount(): void
    {
        $backupDir = sys_get_temp_dir() . '/pouch-backup-retention-test-' . uniqid();

        $storageService = self::getContainer()->get(StorageServiceInterface::class);
        $databaseUrl = (string) ($_ENV['DATABASE_URL'] ?? '');

        $service = new BackupService(
            storageService: $storageService,
            databaseUrl: $databaseUrl,
            backupRootDir: $backupDir,
            retentionCount: 2,
        );

        $service->run();
        $service->run();
        $result = $service->run();

        self::assertSame(1, $result->prunedBackupCount);

        $remaining = glob($backupDir . '/*', GLOB_ONLYDIR);
        self::assertIsArray($remaining);
        self::assertCount(2, $remaining);
    }
}
