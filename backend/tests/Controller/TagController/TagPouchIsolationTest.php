<?php

declare(strict_types = 1);

namespace App\Tests\Controller\TagController;

use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function json_decode;
use function json_encode;
use function sprintf;

// Two users in two different pouches must not see, rename, or reuse the name
// of each other's tags — a cross-pouch id looks exactly like one that
// doesn't exist (404, never 403), and the same name is free to reuse in a
// different pouch (unique constraint is (name, pouch_id), not name alone).
class TagPouchIsolationTest extends WebTest
{
    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $pouchA = $this->databaseMockManager->createPouch('Tag pouch A');
        $pouchB = $this->databaseMockManager->createPouch('Tag pouch B');

        $this->userA = $this->databaseMockManager->createUser(new UserTestDTO('tag-pouch-a@example.com', 'zaq12wsx'), $pouchA);
        $this->userB = $this->databaseMockManager->createUser(new UserTestDTO('tag-pouch-b@example.com', 'zaq12wsx'), $pouchB);
    }

    private function authAs(User $user): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($user));
    }

    public function testManagementListDoesNotIncludeAnotherPouchsTags(): void
    {
        $this->databaseMockManager->createTag('a-only', pouch: $this->userA->getPouch());
        $this->databaseMockManager->createTag('b-only', pouch: $this->userB->getPouch());

        $this->authAs($this->userA);
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/tags/all');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $names = array_column(json_decode((string) $this->webClient->getResponse()->getContent(), true), 'name');
        self::assertContains('a-only', $names);
        self::assertNotContains('b-only', $names);
    }

    public function testRenamingAnotherPouchsTagReturnsNotFound(): void
    {
        $tagB = $this->databaseMockManager->createTag('b-only', pouch: $this->userB->getPouch());

        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/tags/%d/rename', $tagB->getId()),
            content: json_encode(['name' => 'hijacked']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testDeletingAnotherPouchsTagReturnsNotFound(): void
    {
        $tagB = $this->databaseMockManager->createTag('b-only', pouch: $this->userB->getPouch());
        $adminA = $this->databaseMockManager->createUser(new UserTestDTO('tag-pouch-a-admin@example.com', 'zaq12wsx', ['ROLE_ADMIN']), $this->userA->getPouch());

        $this->authAs($adminA);
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/tags/%d', $tagB->getId()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testTheSameNameCanBeUsedInTwoDifferentPouches(): void
    {
        $this->databaseMockManager->createTag('shared-name', pouch: $this->userA->getPouch());

        $this->authAs($this->userB);
        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/tags', content: json_encode(['name' => 'shared-name']));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }
}
