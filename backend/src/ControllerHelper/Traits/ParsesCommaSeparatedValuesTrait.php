<?php

declare(strict_types = 1);

namespace App\ControllerHelper\Traits;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function is_scalar;
use function mb_strtolower;
use function trim;

/**
 * Shared by ItemController (list()'s `categoryIds`/`tags` query filters) and
 * ItemCreateController (parseItemCreateRequestDTO()'s `tags` field) — same
 * comma-separated-string-to-normalized-list parsing either way.
 */
trait ParsesCommaSeparatedValuesTrait
{
    protected function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @return list<string>
     */
    protected function parseCommaSeparatedTags(mixed $raw): array
    {
        $value = $this->nullableString($raw);

        if (null === $value || '' === $value) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $tag): string => mb_strtolower(trim($tag)), explode(',', $value)),
            static fn (string $tag): bool => '' !== $tag,
        ));
    }

    /**
     * @return list<int>
     */
    protected function parseCommaSeparatedIntegers(mixed $raw): array
    {
        $value = $this->nullableString($raw);

        if (null === $value || '' === $value) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $id): int => (int) trim($id), explode(',', $value)),
            static fn (int $id): bool => 0 < $id,
        ));
    }
}
