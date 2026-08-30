<?php

declare(strict_types = 1);

namespace App\Tests\Controller\ItemController;

use App\Entity\Category;
use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ItemCreateNoteControllerTest extends WebTest
{
    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('note-item-user@example.com', 'zaq12wsx'));
        $this->category = $this->databaseMockManager->createCategory('Notes');
    }

    private function authAsUser(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function createNote(string $content, ?string $name = null): array
    {
        $this->authAsUser();

        $body = ['categoryId' => $this->category->getId(), 'content' => $content];
        if (null !== $name) {
            $body['name'] = $name;
        }

        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/items/notes', content: json_encode($body));

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    public function testCreateNoteWithExplicitName(): void
    {
        $item = $this->createNote("# Hello\n\nSome **markdown** content.", 'My note');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('note', $item['type']);
        self::assertSame('My note', $item['name']);
        self::assertSame("# Hello\n\nSome **markdown** content.", $item['noteContent']);
        self::assertSame('completed', $item['processingStatus']);
        self::assertNull($item['trashedAt']);
    }

    public function testCreateNoteDerivesNameFromFirstLineWhenNameOmitted(): void
    {
        $item = $this->createNote("# My Heading\nBody text");

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('My Heading', $item['name']);
    }

    public function testCreateNoteWithBlankContentReturnsBadRequest(): void
    {
        $this->createNote('   ');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);
    }

    public function testCreateNoteWithMissingCategoryReturnsNotFound(): void
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => 999999, 'content' => 'text']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testUpdateNoteContent(): void
    {
        $item = $this->createNote('Original content');

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/items/%d/note', $item['id']),
            content: json_encode(['content' => 'Updated content']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $updated = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame('Updated content', $updated['noteContent']);
        self::assertSame($item['id'], $updated['id']);
    }

    public function testUpdateNoteWithBlankContentReturnsBadRequest(): void
    {
        $item = $this->createNote('Original content');

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/items/%d/note', $item['id']),
            content: json_encode(['content' => '']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->responseTool->testUnprocessableContentRequestResponseData($this->webClient);
    }

    public function testUpdateNoteOnNonNoteItemReturnsBadRequest(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/urls',
            content: json_encode(['categoryId' => $this->category->getId(), 'url' => 'https://example.com/']),
        );
        $urlItem = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/items/%d/note', $urlItem['id']),
            content: json_encode(['content' => 'new content']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);
    }

    public function testGuestCannotCreateNote(): void
    {
        $guest = $this->databaseMockManager->createUser(new UserTestDTO('note-item-guest@example.com', 'zaq12wsx', ['ROLE_GUEST']));
        $this->setAuthCookie($this->databaseMockManager->loginUser($guest));

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'text']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->responseTool->testForbiddenRequestResponseData($this->webClient);
    }

    public function testGuestCannotEditNote(): void
    {
        $item = $this->createNote('Original content');

        $guest = $this->databaseMockManager->createUser(new UserTestDTO('note-item-guest-edit@example.com', 'zaq12wsx', ['ROLE_GUEST']));
        $this->setAuthCookie($this->databaseMockManager->loginUser($guest));

        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/items/%d/note', $item['id']),
            content: json_encode(['content' => 'hacked']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->responseTool->testForbiddenRequestResponseData($this->webClient);
    }

    public function testDeleteNoteMovesItToTrash(): void
    {
        $item = $this->createNote('To be deleted');

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/items/%d', $item['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/items/%d', $item['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
