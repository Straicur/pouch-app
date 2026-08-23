<?php

declare(strict_types = 1);

namespace App\Security;

use App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException\TooManyRequestsException;
use Symfony\Component\HttpFoundation\Request;

interface LoginRateLimiterInterface
{
    /**
     * @throws TooManyRequestsException
     */
    public function consume(Request $request): void;
}
