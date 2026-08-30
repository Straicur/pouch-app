<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\ConflictException;

use App\ExceptionManagement\Exceptions\ExceptionUuidEnum;
use App\ExceptionManagement\Exceptions\Model\ExceptionModel;
use Symfony\Component\HttpFoundation\Response;

final class ConflictExceptionModel extends ExceptionModel
{
    public function __construct(
        string $detail,
        ?int $conflictingItemId = null,
        int $status = Response::HTTP_CONFLICT,
    ) {
        $context = [self::UUID => ExceptionUuidEnum::CONFLICT->value];
        if (null !== $conflictingItemId) {
            $context['conflictingItemId'] = $conflictingItemId;
        }

        parent::__construct(
            title: 'Conflict',
            status: $status,
            detail: $detail,
            context: $context,
        );
    }
}
