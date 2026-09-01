<?php

declare(strict_types = 1);

namespace App\DTO\Mapper;

use App\DTO\Response\TagResponseDTO;
use App\Entity\Tag;

/**
 * Stateless, so — like the rest of src/DTO — it's excluded from the container in
 * services.yaml and just instantiated directly where needed.
 */
final class TagMapper
{
    public static function toResponseDTO(Tag $tag): TagResponseDTO
    {
        return new TagResponseDTO(
            id: $tag->getId(),
            name: $tag->getName(),
        );
    }

    /**
     * @param list<Tag> $tags
     *
     * @return list<TagResponseDTO>
     */
    public static function toResponseDTOList(array $tags): array
    {
        return array_map(
            self::toResponseDTO(...),
            $tags,
        );
    }
}
