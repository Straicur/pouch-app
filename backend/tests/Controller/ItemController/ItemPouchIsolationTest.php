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
     * TagRepository::findInUseOrderedByName() used to list every tag
     * attached to any active item system-wide — GET /api/tags leaked
     * another pouch's tag names to any logged-in user.
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

    /**
     * Część 18 point 1 — the remaining item mutations not already covered by
     * testMutatingAnotherPouchsItemReturnsNotFound() above: replacing tags
     * and overwriting a FILE item's content. Both go through
     * ItemService::getById() internally, same as the rest.
     */
    public function testReplacingTagsOnAnotherPouchsItemReturnsNotFound(): void
    {
        $itemBId = $this->createNoteAs($this->userB, $this->categoryB, 'B note');

        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $itemBId),
            content: json_encode(['tags' => ['sneaky']]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testOverwritingAnotherPouchsFileItemReturnsNotFound(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-isolation-test-');
        file_put_contents($path, 'original content');

        $this->authAs($this->userB);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->categoryB->getId()],
            files: ['file' => new UploadedFile($path, 'b-file.txt', 'text/plain', null, true)],
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $itemB = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $newPath = tempnam(sys_get_temp_dir(), 'pouch-isolation-test-new-');
        file_put_contents($newPath, 'sneaky replacement content');

        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: sprintf('/api/items/%d/file', $itemB['id']),
            files: ['file' => new UploadedFile($newPath, 'sneaky.txt', 'text/plain', null, true)],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    /**
     * Część 18 point 1 — version history and both signed-link families
     * (download/thumbnail) are all generated from a normal, session-scoped
     * request (ItemService::getById()), unlike the actual signed download
     * itself which is deliberately unscoped (see PouchFilterListener's
     * UNFILTERED_ROUTES — a valid signature is its own, independent proof of
     * access, minted only by someone who could already see the item).
     */
    public function testListingAnotherPouchsItemVersionsReturnsNotFound(): void
    {
        $itemBId = $this->createNoteAs($this->userB, $this->categoryB, 'B note');

        $this->authAs($this->userA);
        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/items/%d/versions', $itemBId));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    /**
     * Część 18 point 4 — free-text search must not surface that a matching
     * word exists in another pouch at all, not even as a zero-content
     * "found something" signal.
     */
    public function testSearchDoesNotReturnAnotherPouchsItems(): void
    {
        $this->createNoteAs($this->userB, $this->categoryB, 'crosspouchsearchleak marker');

        $this->authAs($this->userA);
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items?q=crosspouchsearchleak');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame([], $body['items']);
        self::assertSame(0, $body['total']);
    }

    public function testGeneratingADownloadLinkForAnotherPouchsItemReturnsNotFound(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-isolation-test-');
        file_put_contents($path, 'content');

        $this->authAs($this->userB);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->categoryB->getId()],
            files: ['file' => new UploadedFile($path, 'b-file.txt', 'text/plain', null, true)],
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $itemB = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->authAs($this->userA);
        $this->webClient->request(method: Request::METHOD_POST, uri: sprintf('/api/items/%d/download-link', $itemB['id']));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    /**
     * Moving your own item into another pouch's category must 404, same as
     * every other lookup by id — the target category, not just the item
     * itself, has to resolve through the current pouch's PouchFilter.
     */
    public function testMovingAnItemIntoAnotherPouchsCategoryReturnsNotFound(): void
    {
        $itemAId = $this->createNoteAs($this->userA, $this->categoryA, 'A note');

        $this->authAs($this->userA);
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/items/%d/move', $itemAId),
            content: json_encode(['categoryId' => $this->categoryB->getId()]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }
}
