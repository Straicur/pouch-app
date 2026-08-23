<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\NotFoundException;

use App\ExceptionManagement\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class NotFoundException extends ApiException
{
    public function __construct(
        string $message = 'Not Found',
        int $code = Response::HTTP_NOT_FOUND,
        ?Throwable $previous = null,
        ?NotFoundExceptionModel $model = null,
    ) {
        parent::__construct(
            model: $model ?? new NotFoundExceptionModel(detail: $message, status: $code),
            message: $message,
            code: $code,
            previous: $previous
        );
    }
}
