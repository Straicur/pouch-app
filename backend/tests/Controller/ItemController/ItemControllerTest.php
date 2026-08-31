<?php

declare(strict_types = 1);

namespace App\Tests\Controller\ItemController;

use App\Entity\Category;
use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ItemControllerTest extends WebTest
{
    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('item-user@example.com', 'zaq12wsx'));
        $this->category = $this->databaseMockManager->createCategory('Files');
    }

    private function authAsUser(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function createUploadedFile(string $content, string $filename, string $mimeType = 'text/plain'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-item-test-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $filename, $mimeType, null, true);
    }

    private function uploadFile(string $content, string $filename, array $extraFields = []): array
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->category->getId(), ...$extraFields],
            files: ['file' => $this->createUploadedFile($content, $filename)],
        );

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    public function testUploadCreatesItem(): void
    {
        $item = $this->uploadFile('hello world', 'hello.txt', ['name' => 'Hello file']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('Hello file', $item['name']);
        self::assertSame('hello.txt', $item['originalFilename']);
        self::assertSame($this->category->getId(), $item['categoryId']);
        self::assertFalse($item['keepForever']);
        self::assertNotNull($item['expiresAt']);
        self::assertNull($item['trashedAt']);
    }

    public function testUploadDefaultsNameToOriginalFilename(): void
    {
        $item = $this->uploadFile('content', 'no-name-given.txt');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('no-name-given.txt', $item['name']);
    }

    public function testUploadWithOptionalContentSetsNoteContent(): void
    {
        $item = $this->uploadFile('content', 'described.txt', ['content' => 'A short description']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('A short description', $item['noteContent']);
    }

    public function testUploadWithoutContentLeavesNoteContentNull(): void
    {
        $item = $this->uploadFile('content', 'undescribed.txt');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertNull($item['noteContent']);
    }

    public function testUploadWithKeepForeverHasNoExpiry(): void
    {
        $item = $this->uploadFile('content', 'forever.txt', ['keepForever' => '1']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertTrue($item['keepForever']);
        self::assertNull($item['expiresAt']);
    }

    public function testUploadWithTtlPreset(): void
    {
        $item = $this->uploadFile('content', 'short-lived.txt', ['ttlPreset' => '1h']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $expiresAt = new DateTimeImmutable($item['expiresAt']);
        $expected = (new DateTimeImmutable())->add(new DateInterval('PT1H'));
        self::assertEqualsWithDelta($expected->getTimestamp(), $expiresAt->getTimestamp(), 30);
    }

    public function testUploadRejectsDisallowedExtension(): void
    {
        $this->uploadFile('#!/bin/sh', 'script.exe');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);
    }

    // Extension alone is trivial to fake (rename anything to .txt) — the
    // real content's MIME (sniffed server-side, not the client-declared one)
    // has to be checked too (product doc: explicit allow-list of extensions
    // *and* MIME types). "text/html" isn't in the allow-list, unlike plain
    // text — this .txt actually contains an HTML document.
    public function testUploadRejectsDisallowedMimeType(): void
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->category->getId()],
            files: ['file' => $this->createUploadedFile('<html><body>hi</body></html>', 'looks-like.txt', 'text/html')],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);
    }

    public function testUploadWithMissingCategoryReturnsNotFound(): void
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => '999999'],
            files: ['file' => $this->createUploadedFile('content', 'file.txt')],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testUploadWithoutFileReturnsBadRequest(): void
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->category->getId()],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);
    }

    public function testDuplicateContentIsRejected(): void
    {
        $first = $this->uploadFile('identical content', 'first.txt');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->uploadFile('identical content', 'second.txt');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $content = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame($first['id'], $content['context']['conflictingItemId']);
    }

    public function testListReturnsUploadedItem(): void
    {
        $item = $this->uploadFile('content', 'listed.txt');

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertContains($item['id'], array_column($body['items'], 'id'));
    }

    public function testUploadWithTagsAttachesNormalizedTags(): void
    {
        $item = $this->uploadFile('content', 'tagged.txt', ['tags' => 'Foo, bar , FOO']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame(['foo', 'bar'], $item['tags']);
    }

    public function testGetItemExposesHasAccessKey(): void
    {
        $item = $this->uploadFile('content', 'maybe-locked.txt');

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/items/%d', $item['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertFalse($body['hasAccessKey']);

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/access-key', $item['id']),
            content: json_encode(['key' => 'sekret123']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Setting a key doesn't auto-grant the setter — GET is now locked
        // for this same session until it actually unlocks with that key.
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: sprintf('/api/items/%d/unlock', $item['id']),
            content: json_encode(['key' => 'sekret123']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $grant = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_GET,
            uri: sprintf('/api/items/%d', $item['id']),
            server: ['HTTP_X_POUCH_ACCESS_GRANTS' => json_encode([$grant])],
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertTrue($body['hasAccessKey']);
    }

    public function testGetMissingItemReturnsNotFound(): void
    {
        $this->authAsUser();

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testDeleteMovesItemToTrashAndHidesItFromListAndGet(): void
    {
        $item = $this->uploadFile('content', 'to-delete.txt');

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/items/%d', $item['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/items/%d', $item['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items');
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertNotContains($item['id'], array_column($body['items'], 'id'));
    }

    public function testUnauthenticatedRequestReturnsUnauthorized(): void
    {
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->responseTool->testUnauthorizedRequestResponseData($this->webClient);
    }

    public function testGuestCannotUpload(): void
    {
        $guest = $this->databaseMockManager->createUser(new UserTestDTO('item-guest@example.com', 'zaq12wsx', ['ROLE_GUEST']));
        $this->setAuthCookie($this->databaseMockManager->loginUser($guest));

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->category->getId()],
            files: ['file' => $this->createUploadedFile('content', 'file.txt')],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->responseTool->testForbiddenRequestResponseData($this->webClient);
    }
}
