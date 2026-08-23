<?php

declare(strict_types = 1);

namespace App\Security;

use App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException\TooManyRequestsException;
use DateTimeImmutable;
use Override;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class RateLimiterGuard implements RateLimiterGuardInterface
{
    #[Override]
    public function consume(RateLimiterFactory $rateLimiterFactory, string $key): void
    {
        $limit = $rateLimiterFactory->create($key)->consume();

        if (true === $limit->isAccepted()) {
            return;
        }

        $retryAfter = $limit->getRetryAfter()->getTimestamp() - new DateTimeImmutable()->getTimestamp();

        throw new TooManyRequestsException(retryAfter: max(0, $retryAfter));
    }
}
