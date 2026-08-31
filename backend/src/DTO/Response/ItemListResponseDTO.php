<?php

declare(strict_types = 1);

namespace App\DTO\Response;

/**
 * Post-review fix: GET /api/items' paginated envelope — $total is the count
 * matching the filter *before* Part 7 lock-filtering removes any the caller
 * doesn't currently have a grant for (ItemController::list()), so a locked
 * item still counts towards pagination/"how many are there" the same way a
 * page of results that happens to contain one would look one item short
 * without ever being wrong about *why*.
 */
class ItemListResponseDTO
{
    public function __construct(
        /**
         * @var list<ItemSummaryResponseDTO>
         */
        private readonly array $items,
        private readonly int $total,
        private readonly int $page,
        private readonly int $pageSize,
    ) {}

    /**
     * @return list<ItemSummaryResponseDTO>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }
}
