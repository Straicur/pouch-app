<?php

declare(strict_types = 1);

namespace App\Security\Limiter;

use App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException\TooManyRequestsException;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Structurally identical to LoginRateLimiter — same "IP is the limiter key"
 * choice: a wrong-key streak against one category also throttles attempts
 * against any other category/item, which is fine (arguably desirable) for
 * brute-force protection.
 */
final readonly class AccessKeyRateLimiter implements AccessKeyRateLimiterInterface
{
    public function __construct(
        #[Autowire(service: 'limiter.access_key')]
        private RateLimiterFactory $rateLimiterFactory,
        private RateLimiterGuardInterface $rateLimiterGuard,
    ) {}

    /**
     * @throws TooManyRequestsException
     */
    #[Override]
    public function consume(Request $request): void
    {
        $this->rateLimiterGuard->consume($this->rateLimiterFactory, $request->getClientIp() ?? 'unknown');
    }
}
