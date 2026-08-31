<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

use App\Entity\Category;
use App\Entity\Item;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use Symfony\Component\HttpFoundation\Request;

interface AccessKeyGuardInterface
{
    /**
     * Grants travel as a JSON array on this header — a request can need more
     * than one at once (a locked item inside a locked category). The app is
     * stateless (security.yaml), so this is the only place a client-
     * submitted key unlock is remembered between requests. Public on the
     * interface (not just the concrete AccessKeyGuard) so a controller that
     * needs to relay grants some other way (a query param, for a plain
     * navigation that can't set custom headers — see
     * CategoryController::export()) has one name to inject them under
     * without depending on the implementation.
     */
    public const string GRANTS_HEADER = 'X-Pouch-Access-Grants';

    /** @throws ForbiddenException if $category (or an ancestor) has a key and $request carries no valid grant for it */
    public function assertCategoryUnlocked(Category $category, Request $request): void;

    /** @throws ForbiddenException if $item, or its category chain, is locked and $request carries no valid grant */
    public function assertItemUnlocked(Item $item, Request $request): void;

    /** Same check as assertCategoryUnlocked(), without throwing — for filtering lists. */
    public function isCategoryUnlocked(Category $category, Request $request): bool;

    /** Same check as assertItemUnlocked(), without throwing — for filtering lists. */
    public function isItemUnlocked(Item $item, Request $request): bool;

    /**
     * $item's *own* key only — ignores its category chain entirely, unlike
     * isItemUnlocked(). Part 10's "you must know a key to change/remove it"
     * check needs exactly this narrower question; general item access needs
     * the full isItemUnlocked() instead.
     */
    public function isItemOwnKeyUnlocked(Item $item, Request $request): bool;

    /**
     * Post-review fix: every category (bulk, not one-at-a-time) currently
     * locked for $request — a category with an access key set on it or an
     * ancestor, where $request carries no valid grant for that key's
     * holder. Lets a caller exclude locked categories from a query's WHERE
     * clause *before* it runs, instead of fetching a page and filtering
     * locked items out of it afterwards (ItemController::list() used to do
     * exactly that: COUNT/LIMIT/OFFSET ran first, blind to Part 7 locks, so
     * a page could come back empty while unlocked items existed on the next
     * one, and $total leaked how many hidden items existed).
     *
     * @return list<int>
     */
    public function lockedCategoryIds(Request $request): array;
}
