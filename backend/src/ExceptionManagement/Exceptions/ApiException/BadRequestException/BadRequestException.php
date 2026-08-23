<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\BadRequestException;

use App\ExceptionManagement\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BadRequestException extends ApiException
{
    public function __construct(
        string $message = 'Bad Request',
        int $code = Response::HTTP_BAD_REQUEST,
        ?Throwable $previous = null,
        ?BadRequestExceptionModel $model = null,
    ) {
        parent::__construct(
            model: $model ?? new BadRequestExceptionModel(detail: $message, status: $code),
            message: $message,
            code: $code,
            previous: $previous
        );
    }
}
