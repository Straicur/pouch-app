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

class TagControllerTest extends WebTest
{
    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $pouch = $this->databaseMockManager->createPouch('Tag test pouch');
        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('tag-user@example.com', 'zaq12wsx'), $pouch);
        $this->admin = $this->databaseMockManager->createUser(new UserTestDTO('tag-admin@example.com', 'zaq12wsx', ['ROLE_ADMIN']), $pouch);
    }

    private function authAsUser(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function authAsAdmin(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->admin));
    }

    private function createTag(string $name): array
    {
        $this->authAsUser();

        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/tags', content: json_encode(['name' => $name]));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    public function testCreateTagNormalizesNameToTrimmedLowercase(): void
    {
        $tag = $this->createTag('  Work  ');

        self::assertSame('work', $tag['name']);
        self::assertIsInt($tag['id']);
    }

    public function testCreatingADuplicateNameReturnsConflict(): void
    {
        $this->createTag('work');

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/tags', content: json_encode(['name' => 'Work']));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testCreateWithBlankNameReturnsUnprocessable(): void
    {
        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/tags', content: json_encode(['name' => '']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->responseTool->testUnprocessableContentRequestResponseData($this->webClient);
    }

    public function testGuestCannotCreateATag(): void
    {
        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/tags', content: json_encode(['name' => 'nope']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListAllIncludesATagWithNoItems(): void
    {
        $tag = $this->createTag('unused-tag');

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/tags/all');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $ids = array_column(json_decode((string) $this->webClient->getResponse()->getContent(), true), 'id');
        self::assertContains($tag['id'], $ids);
    }

    public function testRenameTag(): void
    {
        $tag = $this->createTag('old-name');

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/tags/%d/rename', $tag['id']),
            content: json_encode(['name' => 'new-name']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $renamed = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame('new-name', $renamed['name']);
        self::assertSame($tag['id'], $renamed['id']);
    }

    public function testRenamingToAnAlreadyUsedNameReturnsConflict(): void
    {
        $this->createTag('taken');
        $tag = $this->createTag('renamable');

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/tags/%d/rename', $tag['id']),
            content: json_encode(['name' => 'taken']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testRenameMissingTagReturnsNotFound(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: '/api/tags/999999/rename',
            content: json_encode(['name' => 'new-name']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testRegularUserCannotDeleteATag(): void
    {
        $tag = $this->createTag('admin-only-delete');

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/tags/%d', $tag['id']));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminDeletingATagRemovesItFromTheManagementList(): void
    {
        $tag = $this->createTag('to-delete');

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/tags/%d', $tag['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/tags/all');
        $ids = array_column(json_decode((string) $this->webClient->getResponse()->getContent(), true), 'id');
        self::assertNotContains($tag['id'], $ids);
    }

    /**
     * item_tag.tag_id is ON DELETE CASCADE (Item::$tags) — deleting a tag
     * untags whatever items had it instead of failing or orphaning anything.
     */
    public function testDeletingATagUntagsItsItemsInsteadOfFailing(): void
    {
        $tag = $this->createTag('cascades');

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->databaseMockManager->createCategory('Tag cascade category', pouch: $this->user->getPouch())->getId(), 'content' => 'tagged', 'keepForever' => true]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $item = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $item['id']),
            content: json_encode(['tags' => ['cascades']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/tags/%d', $tag['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/items/%d', $item['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $refreshed = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame([], $refreshed['tags']);
    }
}
