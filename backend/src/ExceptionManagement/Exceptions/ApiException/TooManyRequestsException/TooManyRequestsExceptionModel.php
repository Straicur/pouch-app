<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException;

use App\ExceptionManagement\Exceptions\ExceptionUuidEnum;
use App\ExceptionManagement\Exceptions\Model\ExceptionModel;
use Symfony\Component\HttpFoundation\Response;

final class TooManyRequestsExceptionModel extends ExceptionModel
{
    public const string RETRY_AFTER = 'retryAfter';

    public function __construct(
        string $detail,
        int $retryAfter,
        int $status = Response::HTTP_TOO_MANY_REQUESTS,
    ) {
        parent::__construct(
            title: 'Too Many Requests',
            status: $status,
            detail: $detail,
            context: [
                self::UUID        => ExceptionUuidEnum::TOO_MANY_REQUESTS->value,
                self::RETRY_AFTER => $retryAfter,
            ]
        );
    }
}
