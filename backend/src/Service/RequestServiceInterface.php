<?php

declare(strict_types = 1);

namespace App\Service;

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
}
