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
 * Post-review fix (conceptual — "trait + service" discussion): this exact
 * try/finally — open the temp file, stream it to php://output, always
 * unlink() regardless of outcome — used to be pasted into
 * CategoryController::export() and AdminController::backup() separately.
 * When a post-review fix (leaked temp files on a mid-stream failure) landed,
 * it had to be applied twice by hand; a third copy for a future
 * "backup as tar.gz" or similar would make that three. One place instead.
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
