<?php

declare(strict_types = 1);

namespace App\Storage;

use App\Exception\StorageException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Override;
use Psr\Log\LoggerInterface;

use function sprintf;

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
}
