<?php

declare(strict_types = 1);

namespace App\Item;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;

use function strlen;
use function trim;

final class NoteValidator
{
    // Generous — this is markdown source text, not a title field.
    private const int MAX_LENGTH = 200_000;

    /**
     * @throws BadRequestException
     */
    public function assertValid(string $content): void
    {
        if ('' === trim($content)) {
            throw new BadRequestException(message: 'note.content_empty');
        }

        if (strlen($content) > self::MAX_LENGTH) {
            throw new BadRequestException(message: 'note.content_too_long');
        }
    }
}
