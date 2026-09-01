<?php

declare(strict_types = 1);

namespace App\Security;

use Override;
use Symfony\Component\HttpFoundation\Cookie;

final readonly class CookieService implements CookieServiceInterface
{
    public const string ACCESS_TOKEN = 'ACCESS_TOKEN';

    public const string REFRESH_TOKEN = 'REFRESH_TOKEN';

    #[Override]
    public function prepareAuthCookie(
        string $name,
        string $token,
        int $expire,
    ): Cookie {
        return new Cookie(
            name: $name,
            value: $token,
            expire: time() + $expire,
            path: '/',
            secure: true,
            httpOnly: true,
            // Lax, not None: a third-party page could otherwise trigger an
            // authenticated state-changing request (e.g. AdminController's
            // bodyless GC-trigger POST) and the browser would attach this
            // cookie. Frontend and API are same-origin (SameSite's "site" is
            // the registrable domain, not the port, so localhost:5174 calling
            // localhost:8080 is already same-site) — Lax needs nothing else
            // to keep working. See OriginCheckListener for the second,
            // independent layer on top of this.
            sameSite: Cookie::SAMESITE_LAX
        );
    }

    #[Override]
    public function prepareLogoutCookie(
        string $name,
    ): Cookie {
        return new Cookie(
            name: $name,
            value: '',
            expire: time() - 3600,
            path: '/',
            secure: true,
            httpOnly: true,
            // Lax, not None: a third-party page could otherwise trigger an
            // authenticated state-changing request (e.g. AdminController's
            // bodyless GC-trigger POST) and the browser would attach this
            // cookie. Frontend and API are same-origin (SameSite's "site" is
            // the registrable domain, not the port, so localhost:5174 calling
            // localhost:8080 is already same-site) — Lax needs nothing else
            // to keep working. See OriginCheckListener for the second,
            // independent layer on top of this.
            sameSite: Cookie::SAMESITE_LAX
        );
    }
}
