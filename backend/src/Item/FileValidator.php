<?php

declare(strict_types = 1);

namespace App\Item;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use Symfony\Contracts\Translation\TranslatorInterface;

use function in_array;
use function pathinfo;
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

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * @throws BadRequestException
     */
    public function assertValid(string $originalFilename, int $size): void
    {
        if (0 >= $size) {
            throw new BadRequestException(message: 'item.file_empty');
        }

        if (self::MAX_SIZE_BYTES < $size) {
            throw new BadRequestException(message: 'item.file_too_large');
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (false === in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new BadRequestException(
                message: $this->translator->trans('item.extension_not_allowed', ['%extension%' => $extension], domain: 'exceptions'),
            );
        }
    }
}
