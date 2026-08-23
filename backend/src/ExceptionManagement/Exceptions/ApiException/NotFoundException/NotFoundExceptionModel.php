<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\NotFoundException;

use App\ExceptionManagement\Exceptions\ExceptionUuidEnum;
use App\ExceptionManagement\Exceptions\Model\ExceptionModel;
use Symfony\Component\HttpFoundation\Response;

final class NotFoundExceptionModel extends ExceptionModel
{
    public function __construct(
        string $detail,
        int $status = Response::HTTP_NOT_FOUND,
    ) {
        parent::__construct(
            title: 'Not Found',
            status: $status,
            detail: $detail,
            context: [self::UUID => ExceptionUuidEnum::NOT_FOUND->value]
        );
    }
}
