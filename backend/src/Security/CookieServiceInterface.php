<?php

declare(strict_types = 1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Cookie;

interface CookieServiceInterface
{
    public function prepareAuthCookie(
        string $name,
        string $token,
        int $expire,
    ): Cookie;

    public function prepareLogoutCookie(
        string $name,
    ): Cookie;
}
