<?php

declare(strict_types = 1);

namespace App\Tests\Controller\ItemController;

use App\Entity\Category;
use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Same as CategoryPouchIsolationTest, for items — isolation goes through the item's category's pouch.
class ItemPouchIsolationTest extends WebTest
{
    private User $userA;

    private User $userB;

    private Category $categoryA;

    private Category $categoryB;

    protected function setUp(): void
    {
        parent::setUp();

        $pouchA = $this->databaseMockManager->createPouch('Pouch A');
        $pouchB = $this->databaseMockManager->createPouch('Pouch B');

        $this->userA = $this->databaseMockManager->createUser(new UserTestDTO('item-pouch-a@example.com', 'zaq12wsx'), $pouchA);
        $this->userB = $this->databaseMockManager->createUser(new UserTestDTO('item-pouch-b@example.com', 'zaq12wsx'), $pouchB);

        $this->categoryA = $this->databaseMockManager->createCategory('A category', pouch: $pouchA);
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
            content: json_encode(['categoryId' => $category->getId(), 'content' => $content, 'keepForever' => true]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $item = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        return $item['id'];
    }

    public function testListDoesNotIncludeAnotherPouchsItems(): void
    {
        $this->createNoteAs($this->userA, $this->categoryA, 'A note');
        $this->createNoteAs($this->userB, $this->categoryB, 'B note');

        $this->authAs($this->userA);
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertCount(1, $body['items']);
        self::assertSame(1, $body['total']);
    }

    public function testGettingAnotherPouchsItemReturnsNotFound(): void
    {
        $itemBId = $this->createNoteAs($this->userB, $this->categoryB, 'B note');

        $this->authAs($this->userA);
        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/items/%d', $itemBId));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testCreatingAnItemInAnotherPouchsCategoryReturnsNotFound(): void
    {
        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->categoryB->getId(), 'content' => 'Sneaky note']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testUploadingAFileIntoAnotherPouchsCategoryReturnsNotFound(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-isolation-test-');
        file_put_contents($path, 'content');

        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->categoryB->getId()],
            files: ['file' => new UploadedFile($path, 'sneaky.txt', 'text/plain', null, true)],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }
}
