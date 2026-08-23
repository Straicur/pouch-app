<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException;

final readonly class ViolationModel
{
    public function __construct(
        private string $propertyPath,
        private string $message,
        private ?string $code = null,
    ) {}

    public function getPropertyPath(): string
    {
        return $this->propertyPath;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }
}
