<?php

declare(strict_types = 1);

namespace App\ControllerHelper\Factory;

use Override;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function fclose;
use function fopen;
use function is_resource;
use function stream_copy_to_stream;
use function unlink;

/**
 * Streams a temporary file to the response and always unlink()s it
 * afterward, success or failure — the one place this try/finally lives, so
 * every caller (CategoryController::export(), AdminController::backup(), any
 * future one) gets the same leak-free cleanup instead of repeating it.
 */
final readonly class StreamedFileResponseFactory implements StreamedFileResponseFactoryInterface
{
    #[Override]
    public function fromTemporaryFile(string $localPath, string $downloadName, string $contentType = 'application/octet-stream'): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($localPath): void {
            try {
                $sourceStream = fopen($localPath, 'rb');
                if (false === is_resource($sourceStream)) {
                    throw new RuntimeException('Could not open the temporary file for reading');
                }

                $outputStream = fopen('php://output', 'wb');
                if (false === is_resource($outputStream)) {
                    fclose($sourceStream);

                    throw new RuntimeException('Could not open php://output for writing');
                }

                stream_copy_to_stream($sourceStream, $outputStream);
                fclose($sourceStream);
                fclose($outputStream);
            } finally {
                @unlink($localPath);
            }
        });

        $response->headers->set('Content-Type', $contentType);
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $downloadName),
        );

        return $response;
    }
}
