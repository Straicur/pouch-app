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

    /**
     * ItemService's mutating methods (updateNoteContent/delete/setFavorite/
     * replaceTags/overwriteFile) all go through getById() internally, scoped
     * to the current pouch by PouchFilter for a normal session request.
     */
    public function testMutatingAnotherPouchsItemReturnsNotFound(): void
    {
        $itemBId = $this->createNoteAs($this->userB, $this->categoryB, 'B note');

        $this->authAs($this->userA);

        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/items/%d/note', $itemBId),
            content: json_encode(['content' => 'sneaky edit']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->webClient->request(method: Request::METHOD_PUT, uri: sprintf('/api/items/%d/favorite', $itemBId));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/items/%d', $itemBId));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Content-hash dedup used to be checked (and DB-uniquely enforced)
     * globally — two pouches could not independently hold the same file, and
     * a 409 conflict response leaked the conflicting item's id/name from a
     * pouch the caller has no access to.
     */
    public function testTwoPouchesCanIndependentlyUploadIdenticalContent(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-isolation-test-');
        file_put_contents($path, 'identical content');

        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->categoryA->getId()],
            files: ['file' => new UploadedFile($path, 'shared.txt', 'text/plain', null, true)],
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->authAs($this->userB);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->categoryB->getId()],
            files: ['file' => new UploadedFile($path, 'shared.txt', 'text/plain', null, true)],
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * TagRepository::findAllOrderedByName() used to list every tag attached
     * to any active item system-wide — GET /api/tags leaked another pouch's
     * tag names to any logged-in user.
     */
    public function testTagListDoesNotIncludeAnotherPouchsTags(): void
    {
        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->categoryA->getId(), 'content' => 'A note', 'tags' => ['pouch-a-tag']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->authAs($this->userB);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->categoryB->getId(), 'content' => 'B note', 'tags' => ['pouch-b-tag']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/tags');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $tags = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame(['pouch-b-tag'], $tags);
    }
}
