<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

/**
 * A signed, time-limited proof that the caller submitted the right key for
 * one resource (see AccessKeyResource). Handed back to the client from
 * AccessKeyController::unlockCategory()/unlockItem() and expected back on the
 * X-Pouch-Access-Grants request header (see AccessKeyGuard) — the app is
 * stateless (security.yaml: stateless: true), so nothing is remembered
 * server-side between the unlock call and the requests that follow it.
 */
final readonly class AccessGrant
{
    public function __construct(
        public string $resource,
        public int $expires,
        public string $signature,
    ) {}
}
