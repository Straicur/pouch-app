<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\ForbiddenException;

use App\ExceptionManagement\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ForbiddenException extends ApiException
{
    public function __construct(
        string $message = 'Forbidden',
        int $code = Response::HTTP_FORBIDDEN,
        ?Throwable $previous = null,
        ?ForbiddenExceptionModel $model = null,
    ) {
        parent::__construct(
            model: $model ?? new ForbiddenExceptionModel(detail: $message, status: $code),
            message: $message,
            code: $code,
            previous: $previous
        );
    }
}
