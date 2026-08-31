<?php

declare(strict_types = 1);

namespace App\Security\Limiter;

use App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException\TooManyRequestsException;
use Symfony\Component\HttpFoundation\Request;

interface AccessKeyRateLimiterInterface
{
    /**
     * @throws TooManyRequestsException
     */
    public function consume(Request $request): void;
}
