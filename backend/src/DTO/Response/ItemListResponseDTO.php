<?php

declare(strict_types = 1);

namespace App\DTO\Response;

/**
 * GET /api/items' paginated envelope. $total is ACL-aware for *category*
 * locks — a locked category the caller has no valid grant for is excluded
 * from the query before COUNT/OFFSET/LIMIT ever run (AccessKeyGuard::
 * lockedCategoryIds(), threaded through ItemController::list() into
 * ItemRepository::findFilteredPage()), so it never leaks how many hidden
 * items exist and a page never comes back short/empty while unlocked items
 * sit on the next one. An item locked only by its own key is a separate
 * case — it's still counted/paginated normally, just redacted to a locked
 * summary (see ItemMapper::toLockedSummaryResponseDTO()).
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
