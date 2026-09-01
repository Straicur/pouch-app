<?php

declare(strict_types = 1);

namespace App\ControllerHelper\Enum;

enum UserRole: string
{
    case GUEST = 'ROLE_GUEST';

    case USER = 'ROLE_USER';

    case ADMIN = 'ROLE_ADMIN';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
