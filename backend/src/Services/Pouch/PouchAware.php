<?php

declare(strict_types = 1);

namespace App\Services\Pouch;

use App\Entity\Pouch;

/**
 * Marks an entity as scoped to a Pouch — PouchFilter checks for this
 * interface (not a specific class) before adding its `pouch_id = ?`
 * constraint, so any future entity opts in just by implementing it and
 * having a `pouch_id` column. Implemented today by Category and Item; User
 * deliberately does not (see PouchFilter's own docblock — an account has to
 * be findable by email *before* its pouch is known, at login, and admin
 * account management is inherently cross-pouch).
 */
interface PouchAware
{
    public function getPouch(): Pouch;
}
