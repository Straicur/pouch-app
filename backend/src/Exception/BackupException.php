<?php

declare(strict_types = 1);

namespace App\Exception;

use RuntimeException;

/**
 * Wraps any failure from App\Services\Backup\BackupServiceInterface — a
 * failed pg_dump/pg_restore/createdb/dropdb process, or a storage error while
 * mirroring the bucket.
 */
class BackupException extends RuntimeException
{
}
