<?php

declare(strict_types = 1);

namespace App\Security;

use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use function rtrim;

final readonly class ConfigService implements ConfigServiceInterface
{
    public function __construct(
        private ParameterBagInterface $config,
        #[Autowire(env: 'PUBLIC_APP_URL')]
        private string $publicBaseUrl,
    ) {}

    #[Override]
    public function getAccessTokenTimeToLive(): int
    {
        return $this->config->get('lexik_jwt_authentication.token_ttl');
    }

    #[Override]
    public function getRefreshTokenTimeToLive(): int
    {
        return $this->config->get('gesdinet_jwt_refresh_token.ttl');
    }

    #[Override]
    public function getPublicBaseUrl(): string
    {
        return rtrim($this->publicBaseUrl, '/');
    }
}
