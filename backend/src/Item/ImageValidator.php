<?php

declare(strict_types = 1);

namespace App\Item;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;

use function in_array;
use function pathinfo;
use function sprintf;
use function strtolower;

use const PATHINFO_EXTENSION;

/**
 * Photo items' own allow-list — separate from FileValidator's general-file
 * one (product doc: explicit allow-list per item type).
 */
final class ImageValidator
{
    /**
     * @var list<string>
     */
    private const array ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private const int MAX_SIZE_BYTES = 25 * 1024 * 1024;

    /**
     * @throws BadRequestException
     */
    public function assertValid(string $originalFilename, int $size): void
    {
        if (0 >= $size) {
            throw new BadRequestException(message: 'Uploaded file is empty');
        }

        if (self::MAX_SIZE_BYTES < $size) {
            throw new BadRequestException(
                message: sprintf('Image exceeds the maximum allowed size of %d bytes', self::MAX_SIZE_BYTES)
            );
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (false === in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new BadRequestException(
                message: sprintf('File extension ".%s" is not allowed for photos', $extension)
            );
        }
    }
}
