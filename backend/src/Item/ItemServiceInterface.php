<?php

declare(strict_types = 1);

namespace App\Item;

use App\Entity\Item;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;

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
        ItemLifecycleOptions $options,
    ): Item;

    /**
     * Created PENDING — ScrapeUrlMessageHandler fills in the OpenGraph
     * metadata/snapshot text asynchronously.
     *
     * @throws NotFoundException   if the category doesn't exist
     * @throws BadRequestException if the URL is malformed
     */
    public function createUrl(
        int $categoryId,
        string $url,
        ItemLifecycleOptions $options,
    ): Item;

    /**
     * Created PENDING — ProcessPhotoMessageHandler fills in the thumbnail and
     * OCR text asynchronously.
     *
     * @throws NotFoundException   if the category doesn't exist
     * @throws BadRequestException if the file/TTL input is invalid
     * @throws ConflictException   if a non-trashed item with identical content already exists
     */
    public function createPhoto(
        int $categoryId,
        string $tmpPath,
        string $originalFilename,
        string $mimeType,
        int $size,
        ItemLifecycleOptions $options,
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
