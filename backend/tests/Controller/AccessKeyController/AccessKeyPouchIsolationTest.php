<?php

declare(strict_types = 1);

namespace App\Tests\Controller\AccessKeyController;

use App\Entity\Category;
use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;
use function json_encode;
use function sprintf;

/**
 * Część 18 point 1 — access keys go through CategoryService::getById()/
 * ItemService::getById() internally (AccessKeyService), scoped to the
 * current pouch by PouchFilter same as every other lookup — this is the
 * dedicated regression test for that, same shape as CategoryPouchIsolationTest/
 * ItemPouchIsolationTest.
 */
class AccessKeyPouchIsolationTest extends WebTest
{
    private User $userA;

    private User $userB;

    private Category $categoryB;

    protected function setUp(): void
    {
        parent::setUp();

        $pouchA = $this->databaseMockManager->createPouch('Access key pouch A');
        $pouchB = $this->databaseMockManager->createPouch('Access key pouch B');

        $this->userA = $this->databaseMockManager->createUser(new UserTestDTO('access-key-pouch-a@example.com', 'zaq12wsx'), $pouchA);
        $this->userB = $this->databaseMockManager->createUser(new UserTestDTO('access-key-pouch-b@example.com', 'zaq12wsx'), $pouchB);
        $this->categoryB = $this->databaseMockManager->createCategory('B category', pouch: $pouchB);
    }

    private function authAs(User $user): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($user));
    }

    private function createNoteAs(User $user, Category $category, string $content): int
    {
        $this->authAs($user);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $category->getId(), 'content' => $content]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        /** @var array{id: int} $body */
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        return $body['id'];
    }

    public function testSettingAnotherPouchsCategoryKeyReturnsNotFound(): void
    {
        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/categories/%d/access-key', $this->categoryB->getId()),
            content: json_encode(['key' => 'sekret123']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testUnlockingAnotherPouchsCategoryReturnsNotFound(): void
    {
        $this->authAs($this->userB);
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/categories/%d/access-key', $this->categoryB->getId()),
            content: json_encode(['key' => 'sekret123']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: sprintf('/api/categories/%d/unlock', $this->categoryB->getId()),
            content: json_encode(['key' => 'sekret123']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testSettingAnotherPouchsItemKeyReturnsNotFound(): void
    {
        $itemB = $this->createNoteAs($this->userB, $this->categoryB, 'B note');

        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/access-key', $itemB),
            content: json_encode(['key' => 'sekret123']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testUnlockingAnotherPouchsItemReturnsNotFound(): void
    {
        $itemB = $this->createNoteAs($this->userB, $this->categoryB, 'B note');

        $this->authAs($this->userB);
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/access-key', $itemB),
            content: json_encode(['key' => 'sekret123']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: sprintf('/api/items/%d/unlock', $itemB),
            content: json_encode(['key' => 'sekret123']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }
}
