<?php

declare(strict_types = 1);

namespace App\Event\EventSubscriber\Pouch;

use App\Services\Pouch\CurrentPouchResolverInterface;
use App\Services\Pouch\Filter\PouchFilter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function in_array;
use function str_starts_with;

/**
 * Enables `pouch_filter` (config/packages/doctrine.yaml, PouchFilter) for
 * the duration of a normal, session-authenticated request — every query
 * against a PouchAware entity (Category, Item) is scoped to the caller's own
 * pouch automatically from here on; CategoryService/ItemService no longer
 * need to check it themselves.
 *
 * Resolves the EntityManager fresh from ManagerRegistry on every call
 * (never injects/caches EntityManagerInterface directly) — Symfony's test
 * client (and, in principle, a long-lived worker) resets the doctrine
 * `kernel.reset`-tagged services between requests, which replaces the
 * *instance* behind `doctrine.orm.entity_manager`; a listener holding onto
 * the old one would keep calling getFilters() on an EntityManager nothing
 * else uses any more, silently leaving the *actually* active one unfiltered
 * (or filtered on some earlier request's since-stale pouch id).
 *
 * Two hooks, not one, for the same "outlives one request" reason:
 * - onKernelRequest(), very high priority so it runs before anything else
 *   on this same event could throw (OriginCheckListener, rate limiters):
 *   unconditionally turns the filter off. Without this, a request that 401s/
 *   403s before ever reaching a controller (never firing kernel.controller
 *   at all) would leave whatever an *earlier, unrelated* request last set it
 *   to still active for every query after.
 * - onKernelController() then turns it back on, deliberately not on
 *   kernel.request: the firewall's JWT authenticator itself listens on
 *   kernel.request, so "is there a logged-in User" isn't answerable yet at
 *   that point — by kernel.controller, authentication has already run.
 *
 * Two deliberate exclusions, both left off:
 * - `/api/admin` (+ /api/admin/users, /api/admin/pouches) — cross-pouch by
 *   nature (browsing/managing across pouches, or a chosen one via the
 *   already-explicit `?pouchId=` params — see AdminController). Enabling
 *   the filter here would scope every admin query to the *admin's own*
 *   pouch, which isn't what any of those actions mean.
 * - The signed-URL download/thumbnail/version-download/public-view family
 *   (see ItemController's class docblock) — its own, independent
 *   authorization channel, deliberately reachable with no session *or*
 *   with one. A coincidentally-logged-in admin/user opening someone else's
 *   signed link must not have it silently 404 because the filter narrowed
 *   the lookup to their own pouch.
 */
final readonly class PouchFilterListener
{
    private const array UNFILTERED_ROUTES = ['item_download', 'item_thumbnail', 'item_version_download', 'item_public_view'];

    public function __construct(
        private ManagerRegistry $managerRegistry,
        private CurrentPouchResolverInterface $currentPouchResolver,
    ) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 2048)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (false === $event->isMainRequest()) {
            return;
        }

        $this->disableIfEnabled();
    }

    #[AsEventListener(event: KernelEvents::CONTROLLER)]
    public function onKernelController(ControllerEvent $event): void
    {
        if (false === $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (str_starts_with($request->getPathInfo(), '/api/admin') || in_array($route, self::UNFILTERED_ROUTES, true)) {
            return;
        }

        try {
            $pouchId = $this->currentPouchResolver->resolve()->getId();
        } catch (LogicException) {
            return;
        }

        /** @var PouchFilter $filter */
        $filter = $this->entityManager()->getFilters()->enable('pouch_filter');
        $filter->setPouchId($pouchId);
    }

    private function disableIfEnabled(): void
    {
        $filters = $this->entityManager()->getFilters();
        if ($filters->isEnabled('pouch_filter')) {
            $filters->disable('pouch_filter');
        }
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $manager */
        $manager = $this->managerRegistry->getManager();

        return $manager;
    }
}
