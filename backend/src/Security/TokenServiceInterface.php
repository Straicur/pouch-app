<?php

declare(strict_types = 1);

namespace App\Security;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

interface TokenServiceInterface
{
    public function createToken(UserInterface $user): string;

    public function createRefreshToken(UserInterface $user): RefreshTokenInterface;

    /**
     * No-op if the token doesn't exist (already used/expired/unknown) — logout
     * should always succeed from the client's point of view.
     */
    public function revokeRefreshToken(string $refreshToken): void;
}
