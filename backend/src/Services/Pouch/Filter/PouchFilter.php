<?php

declare(strict_types = 1);

namespace App\Services\Pouch\Filter;

use App\Services\Pouch\PouchAware;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Override;

/**
 * Registered as `pouch_filter` (config/packages/doctrine.yaml), disabled by
 * default — PouchFilterListener enables it and sets the id for the duration
 * of a normal, session-authenticated request. Doctrine constructs filters
 * itself (not through the container), so — same as setWorkshopId() would be
 * in an analogous multi-tenant setup — the id can only arrive via this
 * setter, called from outside once a "current pouch" is actually resolvable.
 *
 * Applies to any entity implementing PouchAware (Category, Item today) —
 * *not* User: an account has to be findable by email before its pouch is
 * known at all (login), and admin account management is inherently
 * cross-pouch, so User deliberately stays unfiltered.
 *
 * setPouchId() goes through the parent's own setParameter()/getParameter()
 * rather than a plain private property — Doctrine caches the *generated
 * SQL* per DQL string, and that cache key is built from each enabled
 * filter's __toString() (SQLFilter::$parameters, serialized), not from
 * whatever addFilterConstraint() happens to return. A raw property here
 * would return a *different* SQL string for a different pouch while still
 * hashing identical to Doctrine's cache — the previous pouch's id staying
 * baked into a reused cached query for every request after the first one
 * for a given DQL shape, an active data leak across pouches, not just a
 * cosmetic bug.
 */
final class PouchFilter extends SQLFilter
{
    private const string PARAMETER_NAME = 'pouchId';

    public function setPouchId(?int $pouchId): void
    {
        if (null === $pouchId) {
            return;
        }

        $this->setParameter(self::PARAMETER_NAME, $pouchId, Types::INTEGER);
    }

    #[Override]
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (false === $this->hasParameter(self::PARAMETER_NAME)) {
            return '';
        }

        if (null === $targetEntity->reflClass || false === $targetEntity->reflClass->implementsInterface(PouchAware::class)) {
            return '';
        }

        return $targetTableAlias . '.pouch_id = ' . $this->getParameter(self::PARAMETER_NAME);
    }
}
