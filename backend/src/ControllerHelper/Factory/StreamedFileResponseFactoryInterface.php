<?php

declare(strict_types = 1);

namespace App\ControllerHelper\Factory;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface StreamedFileResponseFactoryInterface
{
    /**
     * Streams $localPath out as an attachment named $downloadName, deleting
     * it once the response finishes sending — success or failure alike. For
     * a file a controller action built specifically for this one response
     * (e.g. a temporary ZIP archive) and has no other use for afterwards;
     * not for streaming an object straight out of S3/MinIO (see
     * ItemController::streamedStorageResponse() for that shape instead —
     * there's no local file to clean up in the first place).
     *
     * @throws RuntimeException if $localPath can't be opened, or php://output can't be written to
     */
    public function fromTemporaryFile(string $localPath, string $downloadName, string $contentType = 'application/octet-stream'): StreamedResponse;
}
