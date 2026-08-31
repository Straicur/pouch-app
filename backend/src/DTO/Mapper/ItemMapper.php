<?php

declare(strict_types = 1);

namespace App\DTO\Mapper;

use App\DTO\Response\ItemResponseDTO;
use App\DTO\Response\ItemVersionResponseDTO;
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
