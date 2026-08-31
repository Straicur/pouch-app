<?php

declare(strict_types = 1);

namespace App\Services\Pouch;

use App\Entity\Pouch;
use LogicException;

interface CurrentPouchResolverInterface
{
    /**
     * @throws LogicException if there's no authenticated User — every caller
     *                        sits behind a voter that already requires at
     *                        least ROLE_GUEST, so this is a "should never
     *                        happen" guard, not a normal domain condition
     */
    public function resolve(): Pouch;
}
