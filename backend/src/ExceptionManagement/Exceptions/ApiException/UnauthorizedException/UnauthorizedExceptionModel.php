<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException;

use App\ExceptionManagement\Exceptions\ExceptionUuidEnum;
use App\ExceptionManagement\Exceptions\Model\ExceptionModel;
use Symfony\Component\HttpFoundation\Response;

final class UnauthorizedExceptionModel extends ExceptionModel
{
    public const array  UUID_CONTEXT = [self::UUID => ExceptionUuidEnum::UNAUTHORIZED->value];

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $detail,
        int $status = Response::HTTP_UNAUTHORIZED,
        array $context = [],
    ) {
        parent::__construct(
            title: 'Unauthorized',
            status: $status,
            detail: $detail,
            context: array_merge(self::UUID_CONTEXT, $context)
        );
    }
}
