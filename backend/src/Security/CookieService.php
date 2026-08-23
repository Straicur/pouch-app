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
            sameSite: Cookie::SAMESITE_NONE
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
            sameSite: Cookie::SAMESITE_NONE
        );
    }
}
