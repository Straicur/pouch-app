<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\TechnicalBreakException;

use App\ExceptionManagement\Exceptions\ExceptionUuidEnum;
use App\ExceptionManagement\Exceptions\Model\ExceptionModel;
use Symfony\Component\HttpFoundation\Response;

final class TechnicalBreakExceptionModel extends ExceptionModel
{
    public function __construct(
        string $detail,
        int $status = Response::HTTP_SERVICE_UNAVAILABLE,
    ) {
        parent::__construct(
            title: 'Service Unavailable',
            status: $status,
            detail: $detail,
            context: [self::UUID => ExceptionUuidEnum::TECHNICAL_BREAK->value]
        );
    }
}
