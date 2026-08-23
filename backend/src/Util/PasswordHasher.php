<?php

declare(strict_types = 1);

namespace App\Util;

use SensitiveParameter;

use const PASSWORD_BCRYPT;

final class PasswordHasher
{
    public static function hash(#[SensitiveParameter] string $plainPassword): string
    {
        return password_hash(
            password: $plainPassword,
            algo: PASSWORD_BCRYPT,
            options: [
                'cost' => 15,
            ],
        );
    }
}
