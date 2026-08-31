<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

use Override;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Post-review fix: used to call password_hash()/password_verify() directly —
 * plain bcrypt, same shape as User::$password, but access keys aren't tied
 * to a User so it never went through Symfony's password-hasher factory the
 * way the User's own password now does (see AuthService). Routed through the
 * same factory here instead, under the "access_key" named hasher
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
