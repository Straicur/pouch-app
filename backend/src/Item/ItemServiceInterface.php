<?php

declare(strict_types = 1);

namespace App\Item;

use App\Entity\Item;
use App\Entity\ItemVersion;
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

    /**
     * @throws NotFoundException   if the category doesn't exist
     * @throws BadRequestException if the content is blank/too long
     */
    public function createNote(
        int $categoryId,
        string $content,
        ItemLifecycleOptions $options,
    ): Item;

    /**
     * "Edycja po fakcie" — the one thing a note can do that no other item type
     * can: change its own content after creation.
     *
     * @throws NotFoundException   if the item doesn't exist
     * @throws BadRequestException if the item isn't a note, or the content is blank/too long
     */
    public function updateNoteContent(int $id, string $content): Item;

    /** @throws NotFoundException */
    public function getById(int $id): Item;

    /**
     * @return list<Item>
     */
    public function list(ItemListFilter $filter): array;

    /** @throws NotFoundException */
    public function delete(int $id): void;

    /** @throws NotFoundException */
    public function setFavorite(int $id, bool $favorite): Item;

    /**
     * Replaces the item's full tag set — see Item::setTags().
     *
     * @param list<string> $tagNames
     *
     * @throws NotFoundException
     * @throws BadRequestException if a tag name is too long or too many are given
     */
    public function replaceTags(int $id, array $tagNames): Item;

    /**
     * Overwrites a FILE item's primary asset in place — same id/URL as
     * before (product doc: "bez zmiany ID/adresu w drzewie") — after
     * archiving what it held until now into an ItemVersion row (see
     * listVersions()/getVersion()). The old storage object is kept, not
     * deleted, so the archived version stays downloadable.
     *
     * @throws NotFoundException   if the item doesn't exist
     * @throws BadRequestException if the item isn't a FILE item, or the new file is invalid
     * @throws ConflictException   if a *different*, non-trashed item already has this exact content
     */
    public function overwriteFile(
        int $itemId,
        string $tmpPath,
        string $originalFilename,
        string $mimeType,
        int $size,
    ): Item;

    /**
     * @return list<ItemVersion> oldest first
     *
     * @throws NotFoundException if the item doesn't exist
     */
    public function listVersions(int $itemId): array;

    /** @throws NotFoundException if the item, or that version of it, doesn't exist */
    public function getVersion(int $itemId, int $version): ItemVersion;

    /**
     * Part 10: "lista itemów wygasających w ciągu najbliższych 24h" —
     * generalized to any window (the endpoint decides what "soon" means).
     *
     * @return list<Item> ordered by expiresAt, soonest first
     */
    public function findExpiringBetween(DateTimeImmutable $from, DateTimeImmutable $until): array;

    /**
     * Part 10: "masowe przedłużenie ważności wybranych itemów" — the exact
     * same lifecycle rules createFile()/createUrl()/etc. use (see
     * ItemLifecycleOptions), just applied to items that already exist rather
     * than one being created.
     *
     * @param list<int> $itemIds
     *
     * @return list<Item>
     *
     * @throws NotFoundException   if any $itemIds entry doesn't exist
     * @throws BadRequestException if the resulting expiry isn't in the future
     */
    public function extendExpiry(array $itemIds, ItemLifecycleOptions $options): array;
}
