<?php

declare(strict_types = 1);

namespace App\Security;

use App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException\TooManyRequestsException;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class LoginRateLimiter implements LoginRateLimiterInterface
{
    public function __construct(
        #[Autowire(service: 'limiter.login')]
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
