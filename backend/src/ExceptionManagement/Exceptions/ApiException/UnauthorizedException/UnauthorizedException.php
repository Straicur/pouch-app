<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException;

use App\ExceptionManagement\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UnauthorizedException extends ApiException
{
    public function __construct(
        string $message = 'Unauthorized',
        int $code = Response::HTTP_UNAUTHORIZED,
        ?Throwable $previous = null,
        ?UnauthorizedExceptionModel $model = null,
    ) {
        parent::__construct(
            model: $model ?? new UnauthorizedExceptionModel(detail: $message, status: $code),
            message: $message,
            code: $code,
            previous: $previous
        );
    }
}
