<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\TechnicalBreakException;

use App\ExceptionManagement\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TechnicalBreakException extends ApiException
{
    public function __construct(
        string $message = 'technical_break',
        int $code = Response::HTTP_SERVICE_UNAVAILABLE,
        ?Throwable $previous = null,
        ?TechnicalBreakExceptionModel $model = null,
    ) {
        parent::__construct(
            model: $model ?? new TechnicalBreakExceptionModel(detail: $message, status: $code),
            message: $message,
            code: $code,
            previous: $previous
        );
    }
}
