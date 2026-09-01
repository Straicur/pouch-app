<?php

declare(strict_types = 1);

namespace App\Security;

use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class LogoutService implements LogoutServiceInterface
{
    public function __construct(
        private TokenServiceInterface $tokenService,
        private TokenStorageInterface $tokenStorage,
    ) {}

    #[Override]
    public function logout(Request $request): void
    {
        $session = $request->getSession();
        if ($session->isStarted()) {
            $session->invalidate();
        }

        $refreshToken = $request->cookies->get(CookieService::REFRESH_TOKEN);
        if (null !== $refreshToken) {
            $this->tokenService->revokeRefreshToken($refreshToken);
        }

        $this->tokenStorage->setToken(null);
    }
}
