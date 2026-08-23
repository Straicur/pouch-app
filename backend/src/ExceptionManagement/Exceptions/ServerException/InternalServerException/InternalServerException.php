<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ServerException\InternalServerException;

use App\ExceptionManagement\Exceptions\ServerException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InternalServerException extends ServerException
{
    public function __construct(
        string $message = 'Internal Server',
        int $code = Response::HTTP_INTERNAL_SERVER_ERROR,
        ?Throwable $previous = null,
        ?InternalServerExceptionModel $model = null,
    ) {
        parent::__construct(
            model: $model ?? new InternalServerExceptionModel(detail: $message, status: $code),
            message: $message,
            code: $code,
            previous: $previous
        );
    }
}
