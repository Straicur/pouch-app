<?php

declare(strict_types = 1);

namespace App\Tag;

use App\Entity\Tag;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;

interface TagServiceInterface
{
    /**
     * @return list<Tag>
     */
    public function listAll(): array;

    /**
     * Normalizes (trim + lowercase, dropping blanks/duplicates) and
     * find-or-creates a Tag for each name.
     *
     * @param list<string> $names
     *
     * @return list<Tag>
     *
     * @throws BadRequestException if a name is too long or too many are given
     */
    public function resolveTags(array $names): array;
}
