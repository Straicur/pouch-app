<?php

declare(strict_types = 1);

namespace App\DTO\Mapper;

use App\DTO\Response\ItemResponseDTO;
use App\DTO\Response\ItemSummaryResponseDTO;
use App\DTO\Response\ItemVersionResponseDTO;
use App\DTO\Response\PublicItemResponseDTO;
use App\Entity\Item;
use App\Entity\ItemVersion;
use App\Entity\Tag;
use DateTimeInterface;

use function array_map;
use function array_values;
use function iterator_to_array;

final class ItemMapper
{
    public static function toResponseDTO(Item $item): ItemResponseDTO
    {
        return new ItemResponseDTO(
            id: $item->getId(),
            categoryId: $item->getCategory()->getId(),
            type: $item->getType()->value,
            name: $item->getName(),
            processingStatus: $item->getProcessingStatus()->value,
            processingError: $item->getProcessingError(),
            originalFilename: $item->getOriginalFilename(),
            mimeType: $item->getMimeType(),
            size: $item->getSize(),
            hasThumbnail: null !== $item->getThumbnailStorageKey(),
            url: $item->getUrl(),
            pageTitle: $item->getPageTitle(),
            pageDescription: $item->getPageDescription(),
            extractedText: $item->getExtractedText(),
            noteContent: $item->getNoteContent(),
            favorite: $item->isFavorite(),
            tags: array_values(array_map(
                static fn (Tag $tag): string => $tag->getName(),
                iterator_to_array($item->getTags()),
            )),
            keepForever: $item->isKeepForever(),
            expiresAt: $item->getExpiresAt()?->format(DateTimeInterface::ATOM),
            trashedAt: $item->getTrashedAt()?->format(DateTimeInterface::ATOM),
            createdAt: $item->getCreatedAt()->format(DateTimeInterface::ATOM),
            hasAccessKey: null !== $item->getAccessKeyHash(),
        );
    }

    /**
     * @param list<Item> $items
     *
     * @return list<ItemResponseDTO>
     */
    public static function toResponseDTOList(array $items): array
    {
        return array_map(
            self::toResponseDTO(...),
            $items,
        );
    }

    public static function toSummaryResponseDTO(Item $item): ItemSummaryResponseDTO
    {
        return new ItemSummaryResponseDTO(
            id: $item->getId(),
            categoryId: $item->getCategory()->getId(),
            type: $item->getType()->value,
            name: $item->getName(),
            processingStatus: $item->getProcessingStatus()->value,
            processingError: $item->getProcessingError(),
            originalFilename: $item->getOriginalFilename(),
            mimeType: $item->getMimeType(),
            size: $item->getSize(),
            hasThumbnail: null !== $item->getThumbnailStorageKey(),
            url: $item->getUrl(),
            pageTitle: $item->getPageTitle(),
            pageDescription: $item->getPageDescription(),
            noteContent: $item->getNoteContent(),
            favorite: $item->isFavorite(),
            tags: array_values(array_map(
                static fn (Tag $tag): string => $tag->getName(),
                iterator_to_array($item->getTags()),
            )),
            keepForever: $item->isKeepForever(),
            expiresAt: $item->getExpiresAt()?->format(DateTimeInterface::ATOM),
            trashedAt: $item->getTrashedAt()?->format(DateTimeInterface::ATOM),
            createdAt: $item->getCreatedAt()->format(DateTimeInterface::ATOM),
            locked: false,
        );
    }

    /**
     * Część 13 — an item locked by its own key (category unlocked) no longer
     * disappears from GET /api/items entirely; it stays on the page, but
     * every content-revealing field is redacted so the frontend can show it
     * by name only, with an inline unlock, instead of the item existing
     * silently invisible until you already know its id (see
     * ItemController::list()'s own comment).
     */
    public static function toLockedSummaryResponseDTO(Item $item): ItemSummaryResponseDTO
    {
        return new ItemSummaryResponseDTO(
            id: $item->getId(),
            categoryId: $item->getCategory()->getId(),
            type: $item->getType()->value,
            name: $item->getName(),
            processingStatus: $item->getProcessingStatus()->value,
            processingError: null,
            originalFilename: null,
            mimeType: null,
            size: null,
            hasThumbnail: false,
            url: null,
            pageTitle: null,
            pageDescription: null,
            noteContent: null,
            favorite: false,
            tags: [],
            keepForever: false,
            expiresAt: null,
            trashedAt: null,
            createdAt: $item->getCreatedAt()->format(DateTimeInterface::ATOM),
            locked: true,
        );
    }

    /**
     * @param list<Item> $items
     *
     * @return list<ItemSummaryResponseDTO>
     */
    public static function toSummaryResponseDTOList(array $items): array
    {
        return array_map(
            self::toSummaryResponseDTO(...),
            $items,
        );
    }

    public static function toPublicResponseDTO(Item $item): PublicItemResponseDTO
    {
        return new PublicItemResponseDTO(
            id: $item->getId(),
            type: $item->getType()->value,
            name: $item->getName(),
            originalFilename: $item->getOriginalFilename(),
            mimeType: $item->getMimeType(),
            size: $item->getSize(),
            hasThumbnail: null !== $item->getThumbnailStorageKey(),
            url: $item->getUrl(),
            pageTitle: $item->getPageTitle(),
            pageDescription: $item->getPageDescription(),
            noteContent: $item->getNoteContent(),
            createdAt: $item->getCreatedAt()->format(DateTimeInterface::ATOM),
        );
    }

    public static function toVersionResponseDTO(ItemVersion $itemVersion): ItemVersionResponseDTO
    {
        return new ItemVersionResponseDTO(
            version: $itemVersion->getVersion(),
            originalFilename: $itemVersion->getOriginalFilename(),
            mimeType: $itemVersion->getMimeType(),
            size: $itemVersion->getSize(),
            createdAt: $itemVersion->getCreatedAt()->format(DateTimeInterface::ATOM),
        );
    }

    /**
     * @param list<ItemVersion> $itemVersions
     *
     * @return list<ItemVersionResponseDTO>
     */
    public static function toVersionResponseDTOList(array $itemVersions): array
    {
        return array_map(
            self::toVersionResponseDTO(...),
            $itemVersions,
        );
    }
}
