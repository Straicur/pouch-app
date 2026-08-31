<?php

declare(strict_types = 1);

namespace App\Tests\Controller\CategoryController;

use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Two users in two different pouches must not see or touch each other's
// categories — a cross-pouch id looks exactly like one that doesn't exist (404, never 403).
class CategoryPouchIsolationTest extends WebTest
{
    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $pouchA = $this->databaseMockManager->createPouch('Pouch A');
        $pouchB = $this->databaseMockManager->createPouch('Pouch B');

        $this->userA = $this->databaseMockManager->createUser(new UserTestDTO('pouch-a@example.com', 'zaq12wsx'), $pouchA);
        $this->userB = $this->databaseMockManager->createUser(new UserTestDTO('pouch-b@example.com', 'zaq12wsx'), $pouchB);
    }

    private function authAs(User $user): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($user));
    }

    public function testListDoesNotIncludeAnotherPouchsCategories(): void
    {
        $this->databaseMockManager->createCategory('A only', pouch: $this->userA->getPouch());
        $this->databaseMockManager->createCategory('B only', pouch: $this->userB->getPouch());

        $this->authAs($this->userA);
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/categories');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $names = array_column(json_decode((string) $this->webClient->getResponse()->getContent(), true), 'name');
        self::assertContains('A only', $names);
        self::assertNotContains('B only', $names);
    }

    public function testRenamingAnotherPouchsCategoryReturnsNotFound(): void
    {
        $categoryB = $this->databaseMockManager->createCategory('B only', pouch: $this->userB->getPouch());

        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/categories/%d/rename', $categoryB->getId()),
            content: json_encode(['name' => 'Hijacked']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testCreatingAChildOfAnotherPouchsCategoryReturnsNotFound(): void
    {
        $categoryB = $this->databaseMockManager->createCategory('B only', pouch: $this->userB->getPouch());

        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/categories',
            content: json_encode(['name' => 'Sneaky child', 'parentId' => $categoryB->getId()]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }
}
