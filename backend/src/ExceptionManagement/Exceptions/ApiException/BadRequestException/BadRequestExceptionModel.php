<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\BadRequestException;

use App\ExceptionManagement\Exceptions\ExceptionUuidEnum;
use App\ExceptionManagement\Exceptions\Model\ExceptionModel;
use Symfony\Component\HttpFoundation\Response;

final class BadRequestExceptionModel extends ExceptionModel
{
    public function __construct(
        string $detail,
        int $status = Response::HTTP_BAD_REQUEST,
    ) {
        parent::__construct(
            title: 'Bad Request',
            status: $status,
            detail: $detail,
            context: [self::UUID => ExceptionUuidEnum::BAD_REQUEST->value]
        );
    }
}
