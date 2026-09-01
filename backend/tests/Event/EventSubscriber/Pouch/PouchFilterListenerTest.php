<?php

declare(strict_types = 1);

namespace App\Tests\Event\EventSubscriber\Pouch;

use App\Repository\CategoryRepository;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PouchFilterListener's own two-hook design — onKernelRequest() always
 * turns the filter off, unconditionally, before onKernelController()
 * decides whether to turn it back on for *this* request.
 */
class PouchFilterListenerTest extends WebTest
{
    /**
     * The regression this guards: an earlier one-hook version only ever
     * disabled the filter from onKernelController() itself — a request that
     * never reaches a controller at all (a 401 here) skipped that entirely,
     * so whatever an *earlier* authenticated request had left the filter
     * enabled to (a different pouch) stayed active for every ORM query
     * after, including ones with nothing to do with that later request —
     * a direct repository call, in this test.
     */
    public function testARequestThatNeverReachesTheControllerLeavesTheFilterOff(): void
    {
        $pouchA = $this->databaseMockManager->createPouch('Listener test pouch A');
        $pouchB = $this->databaseMockManager->createPouch('Listener test pouch B');
        $userA = $this->databaseMockManager->createUser(new UserTestDTO('listener-a@example.com', 'zaq12wsx'), $pouchA);
        $categoryB = $this->databaseMockManager->createCategory('B category', pouch: $pouchB);

        $this->setAuthCookie($this->databaseMockManager->loginUser($userA));
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/categories');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        // No auth cookie at all — 401 before kernel.controller ever fires.
        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/categories');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        /** @var CategoryRepository $categoryRepository */
        $categoryRepository = self::getContainer()->get(CategoryRepository::class);
        $found = $categoryRepository->findOneBy(['id' => $categoryB->getId()]);

        self::assertNotNull($found, 'PouchFilter must not still be enabled for pouch A after an unrelated 401.');
        self::assertSame($pouchB->getId(), $found->getPouch()->getId());
    }

    /**
     * `/api/admin` never gets the filter enabled at all, even for an
     * authenticated admin — see PouchFilterListener's own docblock.
     */
    public function testAdminRoutesLeaveTheFilterOffForAnAuthenticatedAdmin(): void
    {
        $pouch = $this->databaseMockManager->createPouch('Listener admin pouch');
        $admin = $this->databaseMockManager->createUser(new UserTestDTO('listener-admin@example.com', 'zaq12wsx', ['ROLE_ADMIN']), $pouch);
        $otherPouch = $this->databaseMockManager->createPouch('Listener other pouch');
        $categoryInOtherPouch = $this->databaseMockManager->createCategory('Other pouch category', pouch: $otherPouch);

        $this->setAuthCookie($this->databaseMockManager->loginUser($admin));
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/storage');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        /** @var CategoryRepository $categoryRepository */
        $categoryRepository = self::getContainer()->get(CategoryRepository::class);
        $found = $categoryRepository->findOneBy(['id' => $categoryInOtherPouch->getId()]);

        self::assertNotNull($found);
    }
}
