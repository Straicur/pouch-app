<?php

declare(strict_types = 1);

namespace App\Category;

use App\Entity\Category;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;

interface CategoryServiceInterface
{
    /**
     * @return list<Category>
     */
    public function list(): array;

    /** @throws NotFoundException */
    public function getById(int $id): Category;

    /** @throws NotFoundException if $parentId is given but doesn't exist */
    public function create(string $name, ?int $parentId): Category;

    /** @throws NotFoundException */
    public function rename(int $id, string $name): Category;

    /**
     * @throws NotFoundException   if $id or $parentId doesn't exist
     * @throws BadRequestException if $parentId is $id itself or one of its descendants
     */
    public function move(int $id, ?int $parentId): Category;

    /** @throws NotFoundException */
    public function delete(int $id): void;
}
