<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException;

use App\ExceptionManagement\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TooManyRequestsException extends ApiException
{
    public function __construct(
        int $retryAfter,
        string $message = 'Too Many Requests',
        int $code = Response::HTTP_TOO_MANY_REQUESTS,
        ?Throwable $previous = null,
        ?TooManyRequestsExceptionModel $model = null,
    ) {
        parent::__construct(
            model: $model ?? new TooManyRequestsExceptionModel(detail: $message, retryAfter: $retryAfter, status: $code),
            message: $message,
            code: $code,
            previous: $previous
        );
    }
}
