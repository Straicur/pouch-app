<?php

declare(strict_types = 1);

namespace App\Services\Pouch;

use App\Entity\Pouch;

final readonly class PouchOverview
{
    public function __construct(
        public Pouch $pouch,
        public int $userCount,
        public int $categoryCount,
        public int $itemCount,
    ) {}
}
