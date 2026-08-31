<?php

declare(strict_types = 1);

namespace App\Security\AccessKey;

use Override;

use function password_hash;
use function password_verify;

use const PASSWORD_BCRYPT;

/**
 * Plain bcrypt, same shape as User::$password — but access keys aren't tied
 * to a User, so this doesn't go through Symfony's password-hasher factory.
 */
final class AccessKeyHasher implements AccessKeyHasherInterface
{
    #[Override]
    public function hash(string $key): string
    {
        return password_hash($key, PASSWORD_BCRYPT);
    }

    #[Override]
    public function verify(string $key, string $hash): bool
    {
        return password_verify($key, $hash);
    }
}
