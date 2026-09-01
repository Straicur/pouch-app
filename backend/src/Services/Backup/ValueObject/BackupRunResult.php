<?php

declare(strict_types = 1);

namespace App\Services\Backup\ValueObject;

final readonly class BackupRunResult
{
    public function __construct(
        public string $backupDir,
        public int $databaseDumpBytes,
        public int $storageFileCount,
        public int $storageBytes,
        public int $prunedBackupCount,
    ) {}
}
