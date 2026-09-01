<?php

declare(strict_types = 1);

namespace App\ControllerHelper\Traits;

use App\Security\CookieService;
use App\Security\CookieServiceInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared by LogoutController and AccountController (delete()/deletePouch(),
 * which log the caller out after removing their own account/pouch) — same
 * cleared-cookie response either way. The actual logout (session/token
 * invalidation) is LogoutServiceInterface's job, not this trait's.
 *
 * @property CookieServiceInterface $cookieService
 */
trait BuildsLogoutResponseTrait
{
    protected function logoutResponse(int $status = Response::HTTP_NO_CONTENT): Response
    {
        $response = new Response(status: $status);
        $response->headers->setCookie($this->cookieService->prepareLogoutCookie(CookieService::ACCESS_TOKEN));
        $response->headers->setCookie($this->cookieService->prepareLogoutCookie(CookieService::REFRESH_TOKEN));

        return $response;
    }
}
