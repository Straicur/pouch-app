<?php

declare(strict_types = 1);

namespace App\Item;

use App\Entity\Item;
use App\Enum\TtlPreset;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use DateTimeImmutable;

interface ItemServiceInterface
{
    /**
     * @throws NotFoundException   if the category doesn't exist
     * @throws BadRequestException if the file/TTL input is invalid
     * @throws ConflictException   if a non-trashed item with identical content already exists
     */
    public function createFile(
        int $categoryId,
        string $tmpPath,
        string $originalFilename,
        string $mimeType,
        int $size,
        ?string $name,
        bool $keepForever,
        ?TtlPreset $ttlPreset,
        ?DateTimeImmutable $customExpiresAt,
    ): Item;

    /** @throws NotFoundException */
    public function getById(int $id): Item;

    /**
     * @return list<Item>
     */
    public function list(?int $categoryId): array;

    /** @throws NotFoundException */
    public function delete(int $id): void;
}
