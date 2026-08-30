<?php

declare(strict_types = 1);

namespace App\Storage;

use App\Exception\StorageException;

interface StorageServiceInterface
{
    /**
     * Streams $stream straight to the bucket — never buffers the whole file in
     * PHP memory, regardless of file size.
     *
     * @param resource $stream
     *
     * @throws StorageException
     */
    public function upload(string $key, $stream): void;

    /**
     * Returns a stream the caller reads from (and must fclose()) — the file
     * content is never loaded into memory as a single string.
     *
     * @return resource
     *
     * @throws StorageException
     */
    public function download(string $key);

    /** @throws StorageException */
    public function delete(string $key): void;

    /** @throws StorageException */
    public function exists(string $key): bool;
}
