<?php

declare(strict_types = 1);

namespace App\Security;

use App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException\TooManyRequestsException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

interface RateLimiterGuardInterface
{
    /**
     * @throws TooManyRequestsException
     */
    public function consume(RateLimiterFactory $rateLimiterFactory, string $key): void;
}
