<?php

declare(strict_types = 1);

namespace App\Event\EventSubscriber;

use App\ControllerHelper\Enum\UserRole;
use App\ExceptionManagement\Exceptions\ApiException\TechnicalBreakException\TechnicalBreakException;
use App\Services\Admin\TechnicalBreakServiceInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Only acts on requests that already resolved to a logged-in User —
 * anonymous requests (login, refresh-token, public share links) pass
 * through untouched, since there's no user to tell apart from an admin
 * anyway. That also means an admin can always still log in and disable the
 * break themselves; nothing here special-cases the login endpoint.
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest')]
final readonly class TechnicalBreakListener
{
    public function __construct(
        private TechnicalBreakServiceInterface $technicalBreakService,
        private Security $security,
    ) {}

    /**
     * @throws TechnicalBreakException
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (false === $event->isMainRequest()) {
            return;
        }

        $activeBreak = $this->technicalBreakService->getActive();
        if (null === $activeBreak) {
            return;
        }

        if (null === $this->security->getUser()) {
            return;
        }

        if (true === $this->security->isGranted(UserRole::ADMIN->value)) {
            return;
        }

        throw new TechnicalBreakException(message: $activeBreak->getMessage() ?? 'technical_break');
    }
}
