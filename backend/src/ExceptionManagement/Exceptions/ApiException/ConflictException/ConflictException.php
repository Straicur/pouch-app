<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\ConflictException;

use App\ExceptionManagement\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ConflictException extends ApiException
{
    public function __construct(
        string $message = 'Conflict',
        int $code = Response::HTTP_CONFLICT,
        ?Throwable $previous = null,
        ?ConflictExceptionModel $model = null,
        ?int $conflictingItemId = null,
    ) {
        parent::__construct(
            model: $model ?? new ConflictExceptionModel(detail: $message, conflictingItemId: $conflictingItemId, status: $code),
            message: $message,
            code: $code,
            previous: $previous
        );
    }
}
