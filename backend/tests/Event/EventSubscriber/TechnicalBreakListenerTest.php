<?php

declare(strict_types = 1);

namespace App\Tests\Event\EventSubscriber;

use App\Entity\TechnicalBreak;
use App\Event\EventSubscriber\TechnicalBreakListener;
use App\ExceptionManagement\Exceptions\ApiException\TechnicalBreakException\TechnicalBreakException;
use App\Services\Admin\TechnicalBreakServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class TechnicalBreakListenerTest extends TestCase
{
    private function requestEvent(bool $mainRequest = true): RequestEvent
    {
        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/items'),
            $mainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );
    }

    private function listener(?TechnicalBreak $activeBreak, ?UserInterface $user, bool $isAdmin = false): TechnicalBreakListener
    {
        $technicalBreakService = $this->createStub(TechnicalBreakServiceInterface::class);
        $technicalBreakService->method('getActive')->willReturn($activeBreak);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturn($isAdmin);

        return new TechnicalBreakListener($technicalBreakService, $security);
    }

    public function testAllowsEverythingWhenNoBreakIsActive(): void
    {
        $this->expectNotToPerformAssertions();

        $this->listener(activeBreak: null, user: $this->createStub(UserInterface::class))->onKernelRequest($this->requestEvent());
    }

    public function testAllowsAnonymousRequestsThroughEvenDuringABreak(): void
    {
        $this->expectNotToPerformAssertions();

        $this->listener(activeBreak: new TechnicalBreak('down for maintenance'), user: null)
            ->onKernelRequest($this->requestEvent());
    }

    public function testAllowsAnAdminThroughDuringABreak(): void
    {
        $this->expectNotToPerformAssertions();

        $this->listener(activeBreak: new TechnicalBreak(null), user: $this->createStub(UserInterface::class), isAdmin: true)
            ->onKernelRequest($this->requestEvent());
    }

    public function testBlocksALoggedInNonAdminDuringABreak(): void
    {
        $this->expectException(TechnicalBreakException::class);

        $this->listener(activeBreak: new TechnicalBreak('down for maintenance'), user: $this->createStub(UserInterface::class), isAdmin: false)
            ->onKernelRequest($this->requestEvent());
    }

    public function testIgnoresSubRequestsEvenDuringABreak(): void
    {
        $this->expectNotToPerformAssertions();

        $this->listener(activeBreak: new TechnicalBreak(null), user: $this->createStub(UserInterface::class), isAdmin: false)
            ->onKernelRequest($this->requestEvent(mainRequest: false));
    }
}
