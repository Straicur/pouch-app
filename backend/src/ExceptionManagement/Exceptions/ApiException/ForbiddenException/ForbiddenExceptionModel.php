<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\ForbiddenException;

use App\ExceptionManagement\Exceptions\ExceptionUuidEnum;
use App\ExceptionManagement\Exceptions\Model\ExceptionModel;
use Symfony\Component\HttpFoundation\Response;

final class ForbiddenExceptionModel extends ExceptionModel
{
    public function __construct(
        string $detail,
        int $status = Response::HTTP_FORBIDDEN,
    ) {
        parent::__construct(
            title: 'Forbidden',
            status: $status,
            detail: $detail,
            context: [self::UUID => ExceptionUuidEnum::FROBIDDEN->value]
        );
    }
}
