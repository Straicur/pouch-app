<?php

declare(strict_types = 1);

namespace App\Tests\Security;

use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\Security\OriginCheckSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Isolated from the HTTP kernel test client on purpose — the subscriber is
 * disabled in test env (.env.test) since none of the ~150 existing
 * functional tests send an Origin/Referer header (same reason the rate
 * limiters are bumped there), so this exercises it directly instead.
 */
final class OriginCheckSubscriberTest extends TestCase
{
    private const string ALLOWED_ORIGIN_PATTERN = '^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$';

    private function subscriber(bool $enabled = true): OriginCheckSubscriber
    {
        return new OriginCheckSubscriber(enabled: $enabled, allowedOriginPattern: self::ALLOWED_ORIGIN_PATTERN);
    }

    private function requestEvent(Request $request): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    public function testAllowsAPostWithAMatchingOrigin(): void
    {
        $request = Request::create('/api/admin/gc', 'POST');
        $request->headers->set('Origin', 'http://localhost:5174');

        $this->expectNotToPerformAssertions();

        $this->subscriber()->onKernelRequest($this->requestEvent($request));
    }

    public function testRejectsAPostWithNeitherOriginNorReferer(): void
    {
        $request = Request::create('/api/admin/gc', 'POST');

        $this->expectException(ForbiddenException::class);

        $this->subscriber()->onKernelRequest($this->requestEvent($request));
    }

    public function testRejectsAPostFromAForeignOrigin(): void
    {
        $request = Request::create('/api/admin/gc', 'POST');
        $request->headers->set('Origin', 'https://evil.example');

        $this->expectException(ForbiddenException::class);

        $this->subscriber()->onKernelRequest($this->requestEvent($request));
    }

    public function testFallsBackToRefererWhenOriginIsMissing(): void
    {
        $request = Request::create('/api/admin/gc', 'POST');
        $request->headers->set('Referer', 'http://localhost:5174/user/items');

        $this->expectNotToPerformAssertions();

        $this->subscriber()->onKernelRequest($this->requestEvent($request));
    }

    public function testIgnoresSafeMethodsEntirely(): void
    {
        $request = Request::create('/api/items', 'GET');

        $this->expectNotToPerformAssertions();

        $this->subscriber()->onKernelRequest($this->requestEvent($request));
    }

    public function testDisabledSubscriberNeverThrows(): void
    {
        $request = Request::create('/api/admin/gc', 'POST');

        $this->expectNotToPerformAssertions();

        $this->subscriber(enabled: false)->onKernelRequest($this->requestEvent($request));
    }
}
