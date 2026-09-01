<?php

declare(strict_types = 1);

namespace App\ControllerHelper\Traits;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use DateTimeImmutable;
use Exception;
use Symfony\Component\HttpFoundation\Request;

use function is_numeric;
use function is_string;
use function max;
use function min;

/**
 * Shared by AdminController (pouchId/limit query filters) and
 * ItemCreateController (customExpiresAt) — same parsing either way.
 */
trait ParsesQueryParamsTrait
{
    /**
     * An unknown/garbage value narrows a query to nothing rather than 404ing,
     * so this only ever parses, never validates.
     */
    protected function nullablePouchId(Request $request): ?int
    {
        $raw = $request->query->get('pouchId');

        return is_string($raw) && is_numeric($raw) ? (int) $raw : null;
    }

    protected function clampLimit(Request $request, int $default = 50, int $max = 200): int
    {
        $limit = $request->query->getInt('limit', $default);

        return max(1, min($limit, $max));
    }

    /**
     * @throws BadRequestException
     */
    protected function parseCustomExpiresAt(?string $expiresAt): ?DateTimeImmutable
    {
        if (null === $expiresAt || '' === $expiresAt) {
            return null;
        }

        try {
            return new DateTimeImmutable($expiresAt);
        } catch (Exception $exception) {
            throw new BadRequestException(message: 'item.expires_at_invalid', previous: $exception);
        }
    }
}
