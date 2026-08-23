<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException;

use App\ExceptionManagement\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UnprocessableContentException extends ApiException
{
    /**
     * @param array<int, ViolationModel> $violations
     */
    public function __construct(
        string $message = 'Unprocessable Content',
        int $code = Response::HTTP_UNPROCESSABLE_ENTITY,
        ?Throwable $previous = null,
        ?UnprocessableContentExceptionModel $model = null,
        array $violations = [],
    ) {
        parent::__construct(
            model: $model ?? new UnprocessableContentExceptionModel(detail: $message, violations: $violations, status: $code),
            message: $message,
            code: $code,
            previous: $previous
        );
    }
}
