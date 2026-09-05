<?php

declare(strict_types = 1);

namespace App\Tests\Event\EventSubscriber\Security;

use App\Event\EventSubscriber\Security\OriginCheckListener;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Isolated from the HTTP kernel test client on purpose — the listener is
 * disabled in test env (.env.test) since none of the ~150 existing
 * functional tests send an Origin/Referer header (same reason the rate
 * limiters are bumped there), so this exercises it directly instead.
 */
final class OriginCheckListenerTest extends TestCase
{
    private const string ALLOWED_ORIGIN_PATTERN = '^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$';

    private function listener(bool $enabled = true): OriginCheckListener
    {
        return new OriginCheckListener(enabled: $enabled, allowedOriginPattern: self::ALLOWED_ORIGIN_PATTERN);
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

        $this->listener()->onKernelRequest($this->requestEvent($request));
    }

    public function testRejectsAPostWithNeitherOriginNorReferer(): void
    {
        $request = Request::create('/api/admin/gc', 'POST');

        $this->expectException(ForbiddenException::class);

        $this->listener()->onKernelRequest($this->requestEvent($request));
    }

    public function testRejectsAPostFromAForeignOrigin(): void
    {
        $request = Request::create('/api/admin/gc', 'POST');
        $request->headers->set('Origin', 'https://evil.example');

        $this->expectException(ForbiddenException::class);

        $this->listener()->onKernelRequest($this->requestEvent($request));
    }

    public function testFallsBackToRefererWhenOriginIsMissing(): void
    {
        $request = Request::create('/api/admin/gc', 'POST');
        $request->headers->set('Referer', 'http://localhost:5174/user/items');

        $this->expectNotToPerformAssertions();

        $this->listener()->onKernelRequest($this->requestEvent($request));
    }

    public function testIgnoresSafeMethodsEntirely(): void
    {
        $request = Request::create('/api/items', 'GET');

        $this->expectNotToPerformAssertions();

        $this->listener()->onKernelRequest($this->requestEvent($request));
    }

    public function testDisabledListenerNeverThrows(): void
    {
        $request = Request::create('/api/admin/gc', 'POST');

        $this->expectNotToPerformAssertions();

        $this->listener(enabled: false)->onKernelRequest($this->requestEvent($request));
    }

    public function testRejectsASubstringMatchEvenWithAnUnanchoredPattern(): void
    {
        $request = Request::create('/api/admin/gc', 'POST');
        $request->headers->set('Origin', 'https://localhost.evil.example');

        $listener = new OriginCheckListener(enabled: true, allowedOriginPattern: 'https?://localhost(:[0-9]+)?');

        $this->expectException(ForbiddenException::class);

        $listener->onKernelRequest($this->requestEvent($request));
    }
}
