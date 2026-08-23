<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException;

use App\ExceptionManagement\Exceptions\ExceptionUuidEnum;
use App\ExceptionManagement\Exceptions\Model\ExceptionModel;
use Symfony\Component\HttpFoundation\Response;

final class UnprocessableContentExceptionModel extends ExceptionModel
{
    /**
     * @param ViolationModel[] $violations
     */
    public function __construct(
        string $detail,
        private readonly array $violations = [],
        int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
    ) {
        parent::__construct(
            title: 'Unprocessable Content',
            status: $status,
            detail: $detail,
            context: [self::UUID => ExceptionUuidEnum::UNPROCESSABLE_CONTENT->value]
        );
    }

    /**
     * @return ViolationModel[]
     */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
