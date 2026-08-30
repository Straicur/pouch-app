<?php

declare(strict_types = 1);

namespace App\DTO\Mapper;

use App\DTO\Response\ItemResponseDTO;
use App\Entity\Item;
use DateTimeInterface;

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
            static fn (Item $item): ItemResponseDTO => self::toResponseDTO($item),
            $items,
        );
    }
}
