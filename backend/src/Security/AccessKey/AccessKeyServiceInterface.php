<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

use App\Entity\Category;
use App\Entity\Item;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException\TooManyRequestsException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use Symfony\Component\HttpFoundation\Request;

interface AccessKeyServiceInterface
{
    /**
     * Walks $category, then its ancestors, and returns the first one that has
     * its own key set — the category whose key actually gates $category (Part
     * 7's "dziedziczony przez podkategorie"). Null if nothing in the chain,
     * including $category itself, has a key: nothing protects it.
     */
    public function findEffectiveKeyHolder(Category $category): ?Category;

    /** @throws NotFoundException */
    public function setCategoryKey(int $categoryId, ?string $key): Category;

    /** @throws NotFoundException */
    public function setItemKey(int $itemId, ?string $key): Item;

    /**
     * @throws NotFoundException        if $categoryId doesn't exist
     * @throws BadRequestException      if $categoryId isn't protected (nothing in its chain has a key)
     * @throws UnauthorizedException    if $key doesn't match
     * @throws TooManyRequestsException
     */
    public function unlockCategory(int $categoryId, string $key, Request $request): AccessGrant;

    /**
     * @throws NotFoundException        if $itemId doesn't exist
     * @throws BadRequestException      if the item has no key of its own
     * @throws UnauthorizedException    if $key doesn't match
     * @throws TooManyRequestsException
     */
    public function unlockItem(int $itemId, string $key, Request $request): AccessGrant;
}
