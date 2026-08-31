<?php

declare(strict_types = 1);

namespace App\Services\Request;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException\UnprocessableContentException;
use Symfony\Component\HttpFoundation\Request;

interface RequestServiceInterface
{
    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T
     *
     * @throws BadRequestException
     * @throws UnprocessableContentException
     */
    public function getRequestBodyContent(Request $request, string $className): object;

    /**
     * Runs the validator over an already-built DTO and throws the same
     * UnprocessableContentException shape getRequestBodyContent() does — for
     * endpoints that can't build their DTO from a JSON body (e.g. multipart
     * form uploads), but still want a consistent validation error response.
     *
     * @throws UnprocessableContentException
     */
    public function validate(object $dto): void;
}
