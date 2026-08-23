<?php

declare(strict_types = 1);

namespace App\Security;

use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\Token\JWTPostAuthenticationToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Override;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class TokenService implements TokenServiceInterface
{
    public function __construct(
        private JWTTokenManagerInterface $JWTTokenManager,
        private TokenStorageInterface $tokenStorage,
        private RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private RefreshTokenManagerInterface $refreshTokenManager,
        private ConfigServiceInterface $configService,
    ) {}

    #[Override]
    public function createToken(UserInterface $user): string
    {
        $jwt = $this->JWTTokenManager->create($user);

        $securityToken = new JWTPostAuthenticationToken(
            $user,
            'main',
            $user->getRoles(),
            $jwt
        );

        $this->tokenStorage->setToken($securityToken);

        return $jwt;
    }

    #[Override]
    public function createRefreshToken(UserInterface $user): RefreshTokenInterface
    {
        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl(
            user: $user,
            ttl: $this->configService->getRefreshTokenTimeToLive()
        );

        $this->refreshTokenManager->save($refreshToken);

        return $refreshToken;
    }
}
