<?php

declare(strict_types = 1);

namespace App\Services\Admin;

use App\Entity\TechnicalBreak;

interface TechnicalBreakServiceInterface
{
    public function getActive(): ?TechnicalBreak;

    /**
     * Updates the message of an already-active break instead of starting a
     * second one — there's only ever one active break at a time.
     */
    public function enable(?string $message): TechnicalBreak;

    /**
     * @return TechnicalBreak|null the deactivated break, or null if none was active
     */
    public function disable(): ?TechnicalBreak;
}
