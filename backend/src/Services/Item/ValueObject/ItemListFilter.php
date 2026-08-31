<?php

declare(strict_types = 1);

namespace App\Services\Item\ValueObject;

/**
 * Every way GET /api/items can be narrowed down — kept as one value object
 * rather than four loose parameters since ItemRepository::findFiltered()
 * combines them all in a single query.
 */
final readonly class ItemListFilter
{
    /**
     * @param list<int>    $categoryIds an item matches if its category is *any* of these
     * @param list<string> $tags        already-normalized (trim + lowercase) tag
     *                                  names — an item matches if it has *any* of them
     */
    public function __construct(
        public array $categoryIds = [],
        public bool $favoriteOnly = false,
        public array $tags = [],
        public ?string $query = null,
    ) {}
}
