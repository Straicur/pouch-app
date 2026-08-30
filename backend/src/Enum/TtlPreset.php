<?php

declare(strict_types = 1);

namespace App\Enum;

use DateInterval;

enum TtlPreset: string
{
    case ONE_HOUR = '1h';

    case SEVEN_DAYS = '7d';

    case THIRTY_DAYS = '30d';

    public function toDateInterval(): DateInterval
    {
        return match ($this) {
            self::ONE_HOUR    => new DateInterval('PT1H'),
            self::SEVEN_DAYS  => new DateInterval('P7D'),
            self::THIRTY_DAYS => new DateInterval('P30D'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
