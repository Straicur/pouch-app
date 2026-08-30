<?php

declare(strict_types = 1);

namespace App\Tests\Controller\CategoryController;

use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The guest/user/admin permission matrix from CategoryVoter, exercised through
 * the real HTTP layer (as opposed to CategoryControllerTest, which covers CRUD
 * correctness using a single ROLE_USER account).
 */
class CategoryPermissionsTest extends WebTest
{
    private function loginAs(string $email, array $roles): void
    {
        $user = $this->databaseMockManager->createUser(new UserTestDTO($email, 'zaq12wsx', $roles));
        $this->setAuthCookie($this->databaseMockManager->loginUser($user));
    }

    public function testGuestCanView(): void
    {
        $this->loginAs('perm-guest-view@example.com', ['ROLE_GUEST']);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/categories');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testGuestCannotCreate(): void
    {
        $this->loginAs('perm-guest-create@example.com', ['ROLE_GUEST']);

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/categories',
            content: json_encode(['name' => 'Should not be created']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->responseTool->testForbiddenRequestResponseData($this->webClient);
    }

    public function testGuestCannotDelete(): void
    {
        $this->loginAs('perm-admin-for-guest-delete@example.com', ['ROLE_ADMIN']);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/categories',
            content: json_encode(['name' => 'Guarded category']),
        );
        $category = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->loginAs('perm-guest-delete@example.com', ['ROLE_GUEST']);
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/categories/%d', $category['id']));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->responseTool->testForbiddenRequestResponseData($this->webClient);
    }

    public function testUserCanCreate(): void
    {
        $this->loginAs('perm-user-create@example.com', ['ROLE_USER']);

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/categories',
            content: json_encode(['name' => 'User made this']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testUserCannotDelete(): void
    {
        $this->loginAs('perm-user-delete@example.com', ['ROLE_USER']);
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/categories',
            content: json_encode(['name' => 'User cannot delete this']),
        );
        $category = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/categories/%d', $category['id']));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->responseTool->testForbiddenRequestResponseData($this->webClient);
    }

    public function testAdminCanCreateAndDelete(): void
    {
        $this->loginAs('perm-admin-full@example.com', ['ROLE_ADMIN']);

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/categories',
            content: json_encode(['name' => 'Admin does everything']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $category = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/categories/%d', $category['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}
