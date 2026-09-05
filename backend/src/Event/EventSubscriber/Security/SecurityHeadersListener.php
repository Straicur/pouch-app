<?php

declare(strict_types = 1);

namespace App\Event\EventSubscriber\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Baseline response headers with no environment-specific value to get
 * wrong — safe to ship before the production domain/TLS setup exists.
 * Content-Security-Policy is deliberately not set here: img-src would need
 * the storage endpoint's public origin (STORAGE_ENDPOINT, MinIO today),
 * which differs per environment and isn't decided for production yet.
 * HSTS is likewise left for when HTTPS is actually terminated — sending it
 * over plain HTTP has no effect and risks a stale directive.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse')]
final readonly class SecurityHeadersListener
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (false === $event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
