<?php

declare(strict_types = 1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;

interface LogoutServiceInterface
{
    /**
     * Invalidates the session, revokes the refresh token cookie carries (if any),
     * and clears the security token storage. Does not touch the HTTP response —
     * building one (e.g. with cleared cookies) is the caller's job.
     */
    public function logout(Request $request): void;
}
