<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ServerException\InternalServerException;

use App\ExceptionManagement\Exceptions\ExceptionUuidEnum;
use App\ExceptionManagement\Exceptions\Model\ExceptionModel;
use Symfony\Component\HttpFoundation\Response;

final class InternalServerExceptionModel extends ExceptionModel
{
    public function __construct(
        string $detail,
        int $status = Response::HTTP_INTERNAL_SERVER_ERROR,
    ) {
        parent::__construct(
            title: 'Internal Server',
            status: $status,
            detail: $detail,
            context: [self::UUID => ExceptionUuidEnum::INTERNAL_SERVER->value]
        );
    }
}
