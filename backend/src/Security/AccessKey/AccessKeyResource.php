<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

/**
 * The resource string signed/verified by SignedUrlService for a category or
 * item access grant. Both AccessKeyService (issuing) and AccessKeyGuard
 * (checking) must agree on the exact same string, hence one shared place.
 *
 * Folds in $keyVersion (bumped by Category/Item::setAccessKeyHash() on every
 * change — see those entities) and $userId (the grant holder) — a grant is
 * only ever valid for the key version it was issued against, from the same
 * account that unlocked it:
 *  - resetting a key bumps the version, so every grant issued for the old
 *    one stops matching immediately (no revocation list needed);
 *  - one user unlocking something doesn't hand a working grant to whoever's
 *    logged into the same browser tab next (sessionStorage grants otherwise
 *    outlive logout — see frontend's accessGrants.ts, cleared on login/logout
 *    as the primary fix, this is the backend's own layer of the same one).
 */
final class AccessKeyResource
{
    public static function forCategory(int $categoryId, int $keyVersion, int $userId): string
    {
        return 'category-key:' . $categoryId . ':v' . $keyVersion . ':u' . $userId;
    }

    public static function forItem(int $itemId, int $keyVersion, int $userId): string
    {
        return 'item-key:' . $itemId . ':v' . $keyVersion . ':u' . $userId;
    }
}
