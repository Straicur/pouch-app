<?php

declare(strict_types = 1);

namespace App\Tests\Event\EventSubscriber\Security;

use App\Event\EventSubscriber\Security\SecurityHeadersListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersListenerTest extends TestCase
{
    private function responseEvent(bool $mainRequest = true): ResponseEvent
    {
        return new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/items'),
            $mainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
            new Response(),
        );
    }

    public function testSetsBaselineHeadersOnAMainRequest(): void
    {
        $event = $this->responseEvent();

        (new SecurityHeadersListener())->onKernelResponse($event);

        $headers = $event->getResponse()->headers;
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $headers->get('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $headers->get('Referrer-Policy'));
    }

    public function testIgnoresSubRequests(): void
    {
        $event = $this->responseEvent(mainRequest: false);

        (new SecurityHeadersListener())->onKernelResponse($event);

        self::assertNull($event->getResponse()->headers->get('X-Content-Type-Options'));
    }
}
