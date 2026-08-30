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
 * Product doc: "jawna lista dozwolonych rozszerzeń/MIME per typ itemu i twardy
 * limit rozmiaru" — this is the allow-list for the general-file item type
 * specifically; other item types (Part 4/5) will get their own.
 */
final class FileValidator
{
    /**
     * @var list<string>
     */
    private const array ALLOWED_EXTENSIONS = [
        'zip', 'rar', '7z', 'tar', 'gz',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
        'txt', 'csv', 'json', 'md',
        'png', 'jpg', 'jpeg', 'gif', 'webp',
        'mp3', 'wav', 'mp4', 'mov', 'webm',
    ];

    private const int MAX_SIZE_BYTES = 100 * 1024 * 1024;

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
                message: sprintf('File exceeds the maximum allowed size of %d bytes', self::MAX_SIZE_BYTES)
            );
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (false === in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new BadRequestException(
                message: sprintf('File extension ".%s" is not allowed for general files', $extension)
            );
        }
    }
}
