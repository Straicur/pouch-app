<?php

declare(strict_types = 1);

namespace App\DTO\Response;

/**
 * GET /api/items' paginated envelope. $total is ACL-aware — a locked
 * category/item the caller has no valid grant for is excluded from the
 * query before COUNT/OFFSET/LIMIT ever run (AccessKeyGuard::
 * lockedCategoryIds()/lockedItemIdsWithOwnKey(), threaded through
 * ItemController::list() into ItemRepository::findFilteredPage()) — an
 * earlier version of this excluded locked items only *after* fetching a
 * page, which both leaked how many hidden items existed (via $total) and
 * could make a page come back short, or empty, while unlocked items sat on
 * the next one.
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
