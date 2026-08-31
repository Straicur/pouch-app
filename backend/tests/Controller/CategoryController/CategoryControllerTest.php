<?php

declare(strict_types = 1);

namespace App\Tests\Controller\CategoryController;

use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CategoryControllerTest extends WebTest
{
    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('category-user@example.com', 'zaq12wsx'));
        $this->admin = $this->databaseMockManager->createUser(
            new UserTestDTO('category-admin@example.com', 'zaq12wsx', ['ROLE_ADMIN'])
        );
    }

    /**
     * Mints a fresh token right before use — TokenService::createToken() also
     * pushes it into the live TokenStorage as a side effect, and the kernel
     * doesn't reboot before the *first* request of a test, so minting both
     * cookies upfront in setUp() would leave whichever was minted last
     * authenticating that first request regardless of which cookie is set.
     */
    private function authAsUser(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function authAsAdmin(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->admin));
    }

    private function createCategory(string $name, ?int $parentId = null): array
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/categories',
            content: json_encode(['name' => $name, 'parentId' => $parentId]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    public function testCreateRootCategory(): void
    {
        $category = $this->createCategory('Movies');

        self::assertSame('Movies', $category['name']);
        self::assertNull($category['parentId']);
        self::assertIsInt($category['id']);
    }

    public function testCreateSubCategory(): void
    {
        $parent = $this->createCategory('Movies');
        $child = $this->createCategory('Sci-Fi', $parent['id']);

        self::assertSame($parent['id'], $child['parentId']);
    }

    public function testCreateWithMissingParentReturnsNotFound(): void
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/categories',
            content: json_encode(['name' => 'Orphan', 'parentId' => 999999]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);

        // The API response carries the actual translated text, not the
        // "category.not_found" key or a hardcoded English string.
        $content = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame('Nie znaleziono kategorii.', $content['detail']);
    }

    public function testCreateWithBlankNameReturnsUnprocessable(): void
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/categories',
            content: json_encode(['name' => '']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->responseTool->testUnprocessableContentRequestResponseData($this->webClient);
    }

    public function testListCategoriesIncludesCreatedOnes(): void
    {
        $category = $this->createCategory('Books');

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/categories');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $list = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        $ids = array_column($list, 'id');
        self::assertContains($category['id'], $ids);
    }

    public function testListExposesHasAccessKey(): void
    {
        $category = $this->createCategory('Maybe locked');

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/categories/%d/access-key', $category['id']),
            content: json_encode(['key' => 'sekret123']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/categories');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $list = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        $byId = [];
        foreach ($list as $row) {
            $byId[$row['id']] = $row;
        }

        self::assertTrue($byId[$category['id']]['hasAccessKey']);
    }

    public function testRenameCategory(): void
    {
        $category = $this->createCategory('Old name');

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/categories/%d/rename', $category['id']),
            content: json_encode(['name' => 'New name']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $renamed = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame('New name', $renamed['name']);
        self::assertSame($category['id'], $renamed['id']);
    }

    public function testRenameMissingCategoryReturnsNotFound(): void
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: '/api/categories/999999/rename',
            content: json_encode(['name' => 'New name']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testMoveCategoryToAnotherParent(): void
    {
        $parentA = $this->createCategory('Parent A');
        $parentB = $this->createCategory('Parent B');
        $child = $this->createCategory('Child', $parentA['id']);

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/categories/%d/move', $child['id']),
            content: json_encode(['parentId' => $parentB['id']]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $moved = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame($parentB['id'], $moved['parentId']);
    }

    public function testMoveCategoryToRoot(): void
    {
        $parent = $this->createCategory('Parent');
        $child = $this->createCategory('Child', $parent['id']);

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/categories/%d/move', $child['id']),
            content: json_encode(['parentId' => null]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $moved = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertNull($moved['parentId']);
    }

    public function testMoveCategoryIntoItselfIsRejected(): void
    {
        $category = $this->createCategory('Self mover');

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/categories/%d/move', $category['id']),
            content: json_encode(['parentId' => $category['id']]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);
    }

    public function testMoveCategoryIntoOwnDescendantIsRejected(): void
    {
        // Only two levels — Część 13 limits categories to one level of
        // subcategories, so a three-level fixture (Grandparent > Parent >
        // Child) can no longer be built through create() at all.
        $parent = $this->createCategory('Parent');
        $child = $this->createCategory('Child', $parent['id']);

        // Moving "Parent" under its own child "Child" would create a cycle.
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/categories/%d/move', $parent['id']),
            content: json_encode(['parentId' => $child['id']]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);
    }

    public function testCreateSubcategoryOfSubcategoryIsRejected(): void
    {
        $root = $this->createCategory('Root');
        $sub = $this->createCategory('Sub', $root['id']);

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/categories',
            content: json_encode(['name' => 'Sub-sub', 'parentId' => $sub['id']]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);
    }

    public function testMoveCategoryWithChildrenUnderAnotherCategoryIsRejected(): void
    {
        $firstRoot = $this->createCategory('First root');
        $this->createCategory('First root child', $firstRoot['id']);
        $secondRoot = $this->createCategory('Second root');

        // "First root" has a child of its own — nesting it under "Second
        // root" would put that child at depth 3.
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/categories/%d/move', $firstRoot['id']),
            content: json_encode(['parentId' => $secondRoot['id']]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);
    }

    public function testAdminCanDeleteCategory(): void
    {
        $category = $this->createCategory('To delete');

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/categories/%d', $category['id']));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/categories');
        $list = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertNotContains($category['id'], array_column($list, 'id'));
    }

    /**
     * Post-review fix: a category with an active item used to be deleted
     * outright (DB cascade), orphaning the item's storage object — see
     * ROADMAP's "Opcjonalne do naprawy" / CategoryService::delete().
     */
    public function testDeletingCategoryWithAnActiveItemFails(): void
    {
        $category = $this->createCategory('Has an item');
        $this->createNote($category['id']);

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/categories/%d', $category['id']));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/categories');
        $list = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertContains($category['id'], array_column($list, 'id'));
    }

    /**
     * Same as above, but the item sits in a descendant, not the category
     * being deleted directly — CategoryService::delete() walks the whole
     * subtree, not just the one row.
     */
    public function testDeletingCategoryWithAnActiveItemInADescendantFails(): void
    {
        $parent = $this->createCategory('Parent');
        $child = $this->createCategory('Child', $parent['id']);
        $this->createNote($child['id']);

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/categories/%d', $parent['id']));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    /**
     * Post-review fix: the check used to only look at *active* items
     * (`trashedAt IS NULL`) — a category holding only trashed-but-not-yet-
     * purged items sailed straight through, and the DB cascade deleted them
     * before ItemGarbageCollector::purgeTrash() ever got a chance to clean
     * up their storage objects.
     */
    public function testDeletingCategoryWithOnlyATrashedUnpurgedItemStillFails(): void
    {
        $category = $this->createCategory('Has a trashed item');
        $item = $this->createNote($category['id']);

        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/items/%d', $item['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/categories/%d', $category['id']));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    private function createNote(int $categoryId): array
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $categoryId, 'content' => 'note content', 'keepForever' => true]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    public function testDeleteMissingCategoryReturnsNotFound(): void
    {
        $this->authAsAdmin();

        $this->webClient->request(method: Request::METHOD_DELETE, uri: '/api/categories/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testUnauthenticatedRequestReturnsUnauthorized(): void
    {
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/categories');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->responseTool->testUnauthorizedRequestResponseData($this->webClient);
    }
}
