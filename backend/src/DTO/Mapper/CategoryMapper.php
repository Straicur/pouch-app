<?php

declare(strict_types = 1);

namespace App\DTO\Mapper;

use App\DTO\Response\CategoryResponseDTO;
use App\Entity\Category;

/**
 * Stateless, so — like the rest of src/DTO — it's excluded from the container in
 * services.yaml and just instantiated directly where needed.
 */
final class CategoryMapper
{
    public static function toResponseDTO(Category $category): CategoryResponseDTO
    {
        return new CategoryResponseDTO(
            id: $category->getId(),
            name: $category->getName(),
            parentId: $category->getParent()?->getId(),
            hasAccessKey: null !== $category->getAccessKeyHash(),
        );
    }

    /**
     * @param list<Category> $categories
     *
     * @return list<CategoryResponseDTO>
     */
    public static function toResponseDTOList(array $categories): array
    {
        return array_map(
            self::toResponseDTO(...),
            $categories,
        );
    }
}
