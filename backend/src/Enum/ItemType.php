<?php

declare(strict_types = 1);

namespace App\Enum;

/**
 * Deliberately not assumed to stay at one case — Part 4/5 of the roadmap add
 * URL/photo/note. Backed by a plain string DB column, so new cases never need
 * a migration (see Item::$type).
 */
enum ItemType: string
{
    case FILE = 'file';

    case URL = 'url';

    case PHOTO = 'photo';
}
