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

use function file_put_contents;
use function http_build_query;
use function json_decode;
use function json_encode;
use function parse_str;
use function parse_url;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

/**
 * Part 9. Reuses the exact signing mechanism download-link/thumbnail-link
 * already have (see ItemDownloadTest) — the only real differences to prove
 * here are: (1) the link is a genuinely absolute URL, not a relative one the
 * frontend's own origin resolves, and (2) the view endpoint works for every
 * item type, not just ones with a file.
 */
class ItemPublicLinkTest extends WebTest
{
    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('public-link-user@example.com', 'zaq12wsx'));
        $this->category = $this->databaseMockManager->createCategory('Public links');
    }

    private function auth(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function createFileItem(string $content, string $filename): array
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-public-link-test-');
        file_put_contents($path, $content);

        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->category->getId()],
            files: ['file' => new UploadedFile($path, $filename, 'text/plain', null, true)],
        );

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    private function createNoteItem(string $content): array
    {
        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => $content, 'keepForever' => true]),
        );

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    private function requestPublicLink(int $itemId): array
    {
        $this->auth();
        $this->webClient->request(method: Request::METHOD_POST, uri: "/api/items/{$itemId}/public-link");

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    private function requestUnauthenticated(string $url): void
    {
        $path = parse_url($url, PHP_URL_PATH);
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        // No auth cookie on purpose — a public link must work standalone.
        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(method: Request::METHOD_GET, uri: $path . '?' . http_build_query($query));
    }

    public function testPublicLinkGenerationRequiresAuth(): void
    {
        $item = $this->createFileItem('hello', 'hello.txt');
        $this->webClient->getCookieJar()->clear();

        $this->webClient->request(method: Request::METHOD_POST, uri: "/api/items/{$item['id']}/public-link");

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testPublicLinkUrlsAreAbsoluteAndIncludeDownloadForAFileItem(): void
    {
        $item = $this->createFileItem('hello', 'hello.txt');

        $link = $this->requestPublicLink($item['id']);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertTrue(str_starts_with($link['viewUrl'], 'http://'), 'viewUrl should be a genuinely absolute URL, not a relative path');
        self::assertNotNull($link['downloadUrl']);
        self::assertTrue(str_starts_with($link['downloadUrl'], 'http://'));
        self::assertNull($link['thumbnailUrl']);
    }

    public function testPublicViewAndDownloadWorkWithoutAnyAuth(): void
    {
        $item = $this->createFileItem('the content', 'file.txt');
        $link = $this->requestPublicLink($item['id']);

        $this->requestUnauthenticated($link['viewUrl']);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $view = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame($item['id'], $view['id']);
        self::assertSame('file.txt', $view['originalFilename']);

        $this->requestUnauthenticated($link['downloadUrl']);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('the content', $this->webClient->getInternalResponse()->getContent());
    }

    public function testPublicViewWorksForANoteItemToo(): void
    {
        $item = $this->createNoteItem('a shared note');
        $link = $this->requestPublicLink($item['id']);

        self::assertNull($link['downloadUrl']);

        $this->requestUnauthenticated($link['viewUrl']);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $view = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame('note', $view['type']);
        self::assertSame('a shared note', $view['noteContent']);
    }

    public function testPublicViewWithTamperedSignatureIsForbidden(): void
    {
        $item = $this->createFileItem('hello', 'hello.txt');
        $link = $this->requestPublicLink($item['id']);

        $path = parse_url($link['viewUrl'], PHP_URL_PATH);
        $query = [];
        parse_str((string) parse_url($link['viewUrl'], PHP_URL_QUERY), $query);
        $query['signature'] = 'not-the-real-signature';

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(method: Request::METHOD_GET, uri: $path . '?' . http_build_query($query));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->responseTool->testForbiddenRequestResponseData($this->webClient);
    }
}
