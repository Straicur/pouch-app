<?php

declare(strict_types = 1);

namespace App\Services\Item\Validator;

use App\Enum\ItemType;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\Services\Item\StorageLimitServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function in_array;
use function pathinfo;
use function strtolower;

use const PATHINFO_EXTENSION;

/**
 * Photo items' own allow-list — separate from FileValidator's general-file
 * one (product doc: explicit allow-list per item type). The size limit is
 * the one thing an admin can override (Part 10) — see StorageLimitService.
 */
final readonly class ImageValidator
{
    /**
     * @var list<string>
     */
    private const array ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * @var list<string>
     */
    private const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        private TranslatorInterface $translator,
        private StorageLimitServiceInterface $storageLimitService,
    ) {}

    /**
     * @throws BadRequestException
     */
    public function assertValid(string $originalFilename, string $mimeType, int $size): void
    {
        if (0 >= $size) {
            throw new BadRequestException(message: 'item.file_empty');
        }

        if ($this->storageLimitService->getMaxSizeBytes(ItemType::PHOTO) < $size) {
            throw new BadRequestException(message: 'item.file_too_large');
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (false === in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new BadRequestException(
                message: $this->translator->trans('item.extension_not_allowed', ['%extension%' => $extension], domain: 'exceptions'),
            );
        }

        if (false === in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new BadRequestException(
                message: $this->translator->trans('item.mime_type_not_allowed', ['%mimeType%' => $mimeType], domain: 'exceptions'),
            );
        }
    }
}
