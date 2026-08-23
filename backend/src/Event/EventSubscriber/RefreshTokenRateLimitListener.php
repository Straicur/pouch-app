<?php

declare(strict_types = 1);

namespace App\Event\EventSubscriber;

use App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException\TooManyRequestsException;
use App\Security\RateLimiterGuardInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 9)]
final readonly class RefreshTokenRateLimitListener
{
    public function __construct(
        #[Autowire(service: 'limiter.refresh_token')]
        private RateLimiterFactory $rateLimiterFactory,
        private RateLimiterGuardInterface $rateLimiterGuard,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @throws TooManyRequestsException
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (false === $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->getPathInfo() !== $this->urlGenerator->generate('gesdinet_jwt_refresh_token')) {
            return;
        }

        $this->rateLimiterGuard->consume($this->rateLimiterFactory, $request->getClientIp() ?? 'unknown');
    }
}
