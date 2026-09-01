<?php

declare(strict_types = 1);

namespace App\Services\Tag;

use App\Entity\Tag;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;

interface TagServiceInterface
{
    /**
     * Tags actually attached to a live item — feeds the item filter/
     * autocomplete UI.
     *
     * @return list<Tag>
     */
    public function listInUse(): array;

    /**
     * Every tag in the pouch, used or not — feeds the tag-management page.
     *
     * @return list<Tag>
     */
    public function listAll(): array;

    /** @throws NotFoundException */
    public function getById(int $id): Tag;

    /**
     * @throws BadRequestException if the name is blank/too long
     * @throws ConflictException   if a tag with this name already exists in the pouch
     */
    public function create(string $name): Tag;

    /**
     * @throws NotFoundException
     * @throws BadRequestException if the name is blank/too long
     * @throws ConflictException   if another tag with this name already exists in the pouch
     */
    public function rename(int $id, string $name): Tag;

    /** @throws NotFoundException */
    public function delete(int $id): void;

    /**
     * Normalizes (trim + lowercase, dropping blanks/duplicates) and
     * find-or-creates a Tag for each name, in the current pouch.
     *
     * @param list<string> $names
     *
     * @return list<Tag>
     *
     * @throws BadRequestException if a name is too long or too many are given
     */
    public function resolveTags(array $names): array;
}
