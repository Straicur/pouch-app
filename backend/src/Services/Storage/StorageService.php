<?php

declare(strict_types = 1);

namespace App\Services\Storage;

use App\Exception\StorageException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Override;
use Psr\Log\LoggerInterface;

use function fclose;
use function fopen;
use function is_resource;
use function sprintf;
use function stream_copy_to_stream;

class StorageService implements StorageServiceInterface
{
    public function __construct(
        private readonly FilesystemOperator $itemStorage,
        private readonly LoggerInterface $logger,
    ) {}

    #[Override]
    public function upload(string $key, $stream): void
    {
        try {
            $this->itemStorage->writeStream($key, $stream);
        } catch (FilesystemException $exception) {
            $this->logger->error($exception->getMessage());
            throw new StorageException(message: sprintf('Unable to upload "%s"', $key), previous: $exception);
        }
    }

    #[Override]
    public function download(string $key)
    {
        try {
            return $this->itemStorage->readStream($key);
        } catch (FilesystemException $exception) {
            $this->logger->error($exception->getMessage());
            throw new StorageException(message: sprintf('Unable to download "%s"', $key), previous: $exception);
        }
    }

    #[Override]
    public function delete(string $key): void
    {
        try {
            $this->itemStorage->delete($key);
        } catch (FilesystemException $exception) {
            $this->logger->error($exception->getMessage());
            throw new StorageException(message: sprintf('Unable to delete "%s"', $key), previous: $exception);
        }
    }

    #[Override]
    public function exists(string $key): bool
    {
        try {
            return $this->itemStorage->fileExists($key);
        } catch (FilesystemException $exception) {
            $this->logger->error($exception->getMessage());
            throw new StorageException(message: sprintf('Unable to check existence of "%s"', $key), previous: $exception);
        }
    }

    #[Override]
    public function uploadFromPath(string $key, string $localPath): void
    {
        $stream = fopen($localPath, 'r');
        if (false === is_resource($stream)) {
            throw new StorageException(message: sprintf('Could not open "%s" for reading', $localPath));
        }

        try {
            $this->upload($key, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    #[Override]
    public function downloadToPath(string $key, string $localPath): void
    {
        $target = fopen($localPath, 'wb');
        if (false === is_resource($target)) {
            throw new StorageException(message: sprintf('Could not open "%s" for writing', $localPath));
        }

        $source = $this->download($key);

        try {
            if (false === stream_copy_to_stream($source, $target)) {
                throw new StorageException(message: sprintf('Failed to copy "%s" to "%s"', $key, $localPath));
            }
        } finally {
            fclose($source);
            fclose($target);
        }
    }
}
