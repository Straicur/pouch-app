<?php

declare(strict_types = 1);

namespace App\Services\Storage;

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

    /**
     * Convenience wrapper around upload() for callers that already have a
     * local file (e.g. a generated thumbnail) instead of an open stream.
     *
     * @throws StorageException
     */
    public function uploadFromPath(string $key, string $localPath): void;

    /**
     * Convenience wrapper around download() that writes straight to a local
     * file — still streamed, never buffered whole in memory.
     *
     * @throws StorageException
     */
    public function downloadToPath(string $key, string $localPath): void;
}
