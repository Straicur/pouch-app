<?php

declare(strict_types = 1);

namespace App\Services\Backup\ValueObject;

final readonly class BackupRestoreTestResult
{
    public function __construct(
        public bool $ok,
        public string $detail,
    ) {}
}
