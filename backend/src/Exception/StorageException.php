<?php

declare(strict_types = 1);

namespace App\Exception;

use RuntimeException;

/**
 * Wraps any Flysystem failure (adapter/network/permission errors) so callers of
 * App\Storage\StorageServiceInterface only ever need to catch one exception type.
 */
class StorageException extends RuntimeException
{
}
