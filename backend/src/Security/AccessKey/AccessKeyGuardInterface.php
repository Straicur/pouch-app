<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

use App\Entity\Category;
use App\Entity\Item;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use Symfony\Component\HttpFoundation\Request;

interface AccessKeyGuardInterface
{
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
}
