<?php

declare(strict_types = 1);

namespace App\Security;

use Override;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final readonly class ConfigService implements ConfigServiceInterface
{
    public function __construct(
        private ParameterBagInterface $config,
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
}
