<?php

declare(strict_types = 1);

namespace App\Services\Backup;

use App\ExceptionManagement\Exceptions\Command\BackupException;
use App\Services\Backup\ValueObject\BackupRestoreTestResult;
use App\Services\Backup\ValueObject\BackupRunResult;
use App\Services\Storage\StorageServiceInterface;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

use function array_filter;
use function array_slice;
use function array_values;
use function bin2hex;
use function count;
use function dirname;
use function filesize;
use function glob;
use function implode;
use function is_dir;
use function is_file;
use function is_numeric;
use function ltrim;
use function mkdir;
use function parse_url;
use function random_bytes;
use function rawurldecode;
use function rawurlencode;
use function rename;
use function rmdir;
use function scandir;
use function sort;
use function sprintf;
use function str_ends_with;
use function unlink;

use const GLOB_ONLYDIR;

class BackupService implements BackupServiceInterface
{
    /**
     * Core tables checked by restoreTest() — enough to prove real rows
     * round-tripped (not just an empty schema), without hardcoding every
     * table in the app. Live counts are read *after* pg_restore finishes, so
     * a legitimate write to the live DB in that window (e.g. a request
     * landing mid-test) can show as a false mismatch — acceptable for a
     * smoke test meant to run right after run(), not as a byte-exact diff.
     */
    private const array VERIFIED_TABLES = ['pouch', 'user', 'category', 'item'];

    private const int PROCESS_TIMEOUT_SECONDS = 300;

    private const string PARTIAL_SUFFIX = '.partial';

    public function __construct(
        private readonly StorageServiceInterface $storageService,
        #[Autowire(env: 'DATABASE_URL')]
        private readonly string $databaseUrl,
        #[Autowire(env: 'BACKUP_DIR')]
        private readonly string $backupRootDir,
        #[Autowire(env: 'int:BACKUP_RETENTION_COUNT')]
        private readonly int $retentionCount,
    ) {}

    #[Override]
    public function run(): BackupRunResult
    {
        $finalDir = $this->backupRootDir . '/' . new DateTimeImmutable()->format('Y-m-d_His');
        $partialDir = $finalDir . self::PARTIAL_SUFFIX;

        // A dump/mirror interrupted mid-way (crash, OOM-kill) would otherwise
        // leave a directory latestBackupDir() could still pick as if it were
        // complete — build under .partial and only rename into place once
        // every step below succeeded. Any `.partial` left by a previous,
        // differently-timestamped interrupted run is stale too, so sweep all
        // of them, not just one that would collide with $partialDir.
        $this->removeStalePartialDirs();

        $this->ensureDirectory($partialDir);

        $dumpPath = $partialDir . '/database.dump';
        $this->runProcess(['pg_dump', '--format=custom', '--file=' . $dumpPath, $this->cliUrl()]);

        $databaseDumpBytes = filesize($dumpPath);
        if (false === $databaseDumpBytes) {
            throw new BackupException(sprintf('Database dump was not written to "%s"', $dumpPath));
        }

        [$storageFileCount, $storageBytes] = $this->mirrorStorage($partialDir . '/storage');

        if (!rename($partialDir, $finalDir)) {
            throw new BackupException(sprintf('Could not finalize backup "%s"', $finalDir));
        }

        return new BackupRunResult(
            backupDir: $finalDir,
            databaseDumpBytes: $databaseDumpBytes,
            storageFileCount: $storageFileCount,
            storageBytes: $storageBytes,
            prunedBackupCount: $this->pruneOldBackups(),
        );
    }

    #[Override]
    public function restoreTest(): BackupRestoreTestResult
    {
        $latestBackupDir = $this->latestBackupDir();
        if (null === $latestBackupDir) {
            throw new BackupException('No backup found to restore-test — run app:backup:run first');
        }

        $dumpPath = $latestBackupDir . '/database.dump';
        if (!is_file($dumpPath)) {
            throw new BackupException(sprintf('Backup "%s" has no database dump', $latestBackupDir));
        }

        $tempDbName = 'pouch_restore_test_' . bin2hex(random_bytes(4));
        $maintenanceUrl = $this->cliUrl('postgres');

        $this->runProcess(['psql', $maintenanceUrl, '-v', 'ON_ERROR_STOP=1', '-c', sprintf('CREATE DATABASE "%s"', $tempDbName)]);

        try {
            $this->runProcess(['pg_restore', '--no-owner', '--dbname=' . $this->cliUrl($tempDbName), $dumpPath]);

            return $this->compareRowCounts($tempDbName);
        } finally {
            // WITH (FORCE) (PG 13+) drops any lingering connection this call
            // itself opened for the row-count comparison above — otherwise
            // "database is being accessed by other users" would fail the drop.
            $this->runProcess(['psql', $maintenanceUrl, '-v', 'ON_ERROR_STOP=1', '-c', sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $tempDbName)]);
        }
    }

    /**
     * @return array{int, int} [file count, total bytes]
     */
    private function mirrorStorage(string $storageDir): array
    {
        $fileCount = 0;
        $totalBytes = 0;

        foreach ($this->storageService->listAllKeys() as $key) {
            $localPath = $storageDir . '/' . $key;
            $this->ensureDirectory(dirname($localPath));

            $this->storageService->downloadToPath($key, $localPath);

            $size = filesize($localPath);
            $totalBytes += false !== $size ? $size : 0;
            ++$fileCount;
        }

        return [$fileCount, $totalBytes];
    }

    private function compareRowCounts(string $tempDbName): BackupRestoreTestResult
    {
        // Both brand-new connections, not the injected $this->connection —
        // that one may already be sitting inside a long-running transaction
        // (a console command isn't guaranteed autocommit-fresh, and under
        // DAMADoctrineTestBundle in the test suite it never is), which would
        // compare against a stale snapshot instead of current reality.
        $liveConnection = DriverManager::getConnection($this->dbalConnectionParams($this->liveDbName()));
        $tempConnection = DriverManager::getConnection($this->dbalConnectionParams($tempDbName));

        try {
            $mismatches = [];

            foreach (self::VERIFIED_TABLES as $table) {
                $liveRaw = $liveConnection->fetchOne(sprintf('SELECT COUNT(*) FROM "%s"', $table));
                $restoredRaw = $tempConnection->fetchOne(sprintf('SELECT COUNT(*) FROM "%s"', $table));
                $liveCount = is_numeric($liveRaw) ? (int) $liveRaw : 0;
                $restoredCount = is_numeric($restoredRaw) ? (int) $restoredRaw : 0;

                if ($liveCount !== $restoredCount) {
                    $mismatches[] = sprintf('%s: live=%d restored=%d', $table, $liveCount, $restoredCount);
                }
            }

            if ([] !== $mismatches) {
                return new BackupRestoreTestResult(ok: false, detail: implode('; ', $mismatches));
            }

            return new BackupRestoreTestResult(ok: true, detail: sprintf('Row counts match for: %s', implode(', ', self::VERIFIED_TABLES)));
        } finally {
            $liveConnection->close();
            $tempConnection->close();
        }
    }

    private function removeStalePartialDirs(): void
    {
        $entries = glob($this->backupRootDir . '/*' . self::PARTIAL_SUFFIX, GLOB_ONLYDIR);
        if (false === $entries) {
            return;
        }

        foreach ($entries as $dir) {
            $this->removeDirectory($dir);
        }
    }

    private function pruneOldBackups(): int
    {
        $entries = $this->completedBackupDirs();

        $excess = count($entries) - $this->retentionCount;
        if (0 >= $excess) {
            return 0;
        }

        foreach (array_slice($entries, 0, $excess) as $dir) {
            $this->removeDirectory($dir);
        }

        return $excess;
    }

    private function latestBackupDir(): ?string
    {
        $entries = $this->completedBackupDirs();

        return [] === $entries ? null : $entries[count($entries) - 1];
    }

    /**
     * Backup directories, oldest first (names are Y-m-d_His, so a lexical
     * sort is also chronological) — excludes a `.partial` one left behind by
     * an interrupted run() that never made it to the atomic rename.
     *
     * @return list<string>
     */
    private function completedBackupDirs(): array
    {
        $entries = glob($this->backupRootDir . '/*', GLOB_ONLYDIR);
        if (false === $entries) {
            return [];
        }

        $entries = array_values(array_filter($entries, static fn (string $dir): bool => !str_ends_with($dir, self::PARTIAL_SUFFIX)));
        sort($entries);

        return $entries;
    }

    private function ensureDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0o755, recursive: true) && !is_dir($dir)) {
            throw new BackupException(sprintf('Could not create directory "%s"', $dir));
        }
    }

    private function removeDirectory(string $dir): void
    {
        $items = scandir($dir);
        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item) {
                continue;
            }

            if ('..' === $item) {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * DATABASE_URL carries Doctrine-only query params (serverVersion,
     * charset) that libpq's own connection-string parser rejects outright —
     * pg_dump/psql/pg_restore all get a stripped-down URL instead. $dbName
     * defaults to DATABASE_URL's own database (the normal case, dumping the
     * live DB); restoreTest() overrides it to reach the "postgres"
     * maintenance DB or its own throwaway one instead.
     */
    private function cliUrl(?string $dbName = null): string
    {
        $parts = $this->parsedDatabaseUrl();

        $userInfo = rawurlencode($parts['user']) . ('' !== $parts['pass'] ? ':' . rawurlencode($parts['pass']) : '');

        return sprintf('postgresql://%s@%s:%d/%s', $userInfo, $parts['host'], $parts['port'], $dbName ?? $parts['dbname']);
    }

    private function liveDbName(): string
    {
        return $this->parsedDatabaseUrl()['dbname'];
    }

    /**
     * DriverManager::getConnection() doesn't run a bare 'url' entry through
     * the same DSN-parsing Doctrine's own bundle config does — without an
     * explicit host/port/dbname it silently falls back to a local socket
     * instead of DATABASE_URL's actual "db" host.
     *
     * @return array{driver: 'pdo_pgsql', host: string, port: int, user: string, password: string, dbname: string}
     */
    private function dbalConnectionParams(string $dbName): array
    {
        $parts = $this->parsedDatabaseUrl();

        return [
            'driver'   => 'pdo_pgsql',
            'host'     => $parts['host'],
            'port'     => $parts['port'],
            'user'     => $parts['user'],
            'password' => $parts['pass'],
            'dbname'   => $dbName,
        ];
    }

    /**
     * Single parse of DATABASE_URL, user/pass already percent-decoded to
     * their literal values — parse_url() itself does not decode them, so
     * callers that need a URL again (cliUrl()) must rawurlencode() this back
     * exactly once. Getting that wrong previously double-encoded any
     * password containing a percent-encoded character (parse_url()'s
     * still-encoded value fed straight into another rawurlencode()), and
     * separately fed the still-encoded value to DBAL, which wants the
     * literal password, not a URL-encoded one.
     *
     * @return array{host: string, port: int, user: string, pass: string, dbname: string}
     */
    private function parsedDatabaseUrl(): array
    {
        $parts = parse_url($this->databaseUrl);
        if (false === $parts || !isset($parts['host'], $parts['user'])) {
            throw new BackupException('Could not parse DATABASE_URL');
        }

        return [
            'host'   => $parts['host'],
            'port'   => $parts['port'] ?? 5432,
            'user'   => rawurldecode($parts['user']),
            'pass'   => isset($parts['pass']) ? rawurldecode($parts['pass']) : '',
            'dbname' => ltrim($parts['path'] ?? '', '/'),
        ];
    }

    /**
     * @param non-empty-list<string> $command
     */
    private function runProcess(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $process->run();

        if (!$process->isSuccessful()) {
            // $process->getErrorOutput(), never the command array itself —
            // the last element of several of these is a libpq connection URI
            // carrying the DB password.
            throw new BackupException(sprintf('%s failed: %s', $command[0], $process->getErrorOutput()));
        }
    }
}
