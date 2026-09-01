<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

use Override;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Access keys aren't tied to a User, so this can't go through
 * UserPasswordHasherInterface (see AuthService) — routed through the same
 * password-hasher factory instead, under the "access_key" named hasher
 * (config/packages/security.yaml — algorithm/cost centrally configured
 * there, not hardcoded here, and reduced cost in test env same as User's).
 */
final readonly class AccessKeyHasher implements AccessKeyHasherInterface
{
    private const string HASHER_NAME = 'access_key';

    public function __construct(
        private PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {}

    #[Override]
    public function hash(string $key): string
    {
        return $this->passwordHasherFactory->getPasswordHasher(self::HASHER_NAME)->hash($key);
    }

    #[Override]
    public function verify(string $key, string $hash): bool
    {
        return $this->passwordHasherFactory->getPasswordHasher(self::HASHER_NAME)->verify($hash, $key);
    }
}
