<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\Command;

use RuntimeException;

/**
 * Wraps any failure from App\Services\Backup\BackupServiceInterface — a
 * failed pg_dump/pg_restore/createdb/dropdb process, or a storage error while
 * mirroring the bucket.
 */
class BackupException extends RuntimeException
{
}
