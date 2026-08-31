<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

/**
 * The resource string signed/verified by SignedUrlService for a category or
 * item access grant. Both AccessKeyService (issuing) and AccessKeyGuard
 * (checking) must agree on the exact same string, hence one shared place.
 */
final class AccessKeyResource
{
    public static function forCategory(int $categoryId): string
    {
        return 'category-key:' . $categoryId;
    }

    public static function forItem(int $itemId): string
    {
        return 'item-key:' . $itemId;
    }
}
