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
        $grandparent = $this->createCategory('Grandparent');
        $parent = $this->createCategory('Parent', $grandparent['id']);
        $child = $this->createCategory('Child', $parent['id']);

        // Moving "Grandparent" under its own grandchild "Child" would create a cycle.
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/categories/%d/move', $grandparent['id']),
            content: json_encode(['parentId' => $child['id']]),
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
