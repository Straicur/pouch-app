<?php

declare(strict_types = 1);

namespace App\Item;

use RuntimeException;

interface ThumbnailServiceInterface
{
    /**
     * Reads the image at $sourcePath and writes a resized JPEG thumbnail to a
     * new temp file, whose path is returned — the caller owns it and must
     * delete it once done (upload to storage, etc).
     *
     * @throws RuntimeException
     */
    public function generate(string $sourcePath, string $mimeType): string;
}
