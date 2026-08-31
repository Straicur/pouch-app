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
use function json_decode;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;

/**
 * Part 8. Integration test per ROADMAP.md: upload → overwrite → old version
 * still reachable from the history, item id/URL unchanged.
 */
class ItemVersioningTest extends WebTest
{
    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('item-version-user@example.com', 'zaq12wsx'));
        $this->category = $this->databaseMockManager->createCategory('Versioned files');
    }

    private function auth(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function uploadedFile(string $content, string $filename, string $mimeType = 'text/plain'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-item-version-test-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $filename, $mimeType, null, true);
    }

    private function createFileItem(string $content, string $filename): array
    {
        $this->auth();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->category->getId()],
            files: ['file' => $this->uploadedFile($content, $filename)],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    private function overwriteFileItem(int $itemId, string $content, string $filename): array
    {
        $this->auth();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: "/api/items/{$itemId}/file",
            files: ['file' => $this->uploadedFile($content, $filename)],
        );

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    public function testOverwritingKeepsTheSameIdAndUpdatesCurrentContent(): void
    {
        $original = $this->createFileItem('version one content', 'report.txt');

        $overwritten = $this->overwriteFileItem($original['id'], 'version two content', 'report-v2.txt');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame($original['id'], $overwritten['id']);
        self::assertSame('report-v2.txt', $overwritten['originalFilename']);
        self::assertSame(strlen('version two content'), $overwritten['size']);
    }

    public function testOverwritingArchivesThePreviousVersionAndItStaysDownloadable(): void
    {
        $original = $this->createFileItem('version one content', 'report.txt');
        $this->overwriteFileItem($original['id'], 'version two content', 'report-v2.txt');

        $this->auth();
        $this->webClient->request(method: Request::METHOD_GET, uri: "/api/items/{$original['id']}/versions");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $versions = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        self::assertCount(1, $versions);
        self::assertSame(1, $versions[0]['version']);
        self::assertSame('report.txt', $versions[0]['originalFilename']);
        self::assertSame(strlen('version one content'), $versions[0]['size']);

        $this->auth();
        $this->webClient->request(method: Request::METHOD_POST, uri: "/api/items/{$original['id']}/versions/1/download-link");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $link = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->webClient->request(method: Request::METHOD_GET, uri: $link['url']);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        // StreamedResponse::getContent() always returns false (content isn't
        // buffered) — the internal response captures what was actually streamed.
        self::assertSame('version one content', $this->webClient->getInternalResponse()->getContent());
    }

    public function testMultipleOverwritesBuildAnOrderedHistory(): void
    {
        $item = $this->createFileItem('v1', 'file.txt');
        $this->overwriteFileItem($item['id'], 'v2', 'file.txt');
        $this->overwriteFileItem($item['id'], 'v3', 'file.txt');

        $this->auth();
        $this->webClient->request(method: Request::METHOD_GET, uri: "/api/items/{$item['id']}/versions");
        $versions = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        self::assertCount(2, $versions);
        self::assertSame([1, 2], [$versions[0]['version'], $versions[1]['version']]);

        $this->auth();
        $this->webClient->request(method: Request::METHOD_GET, uri: "/api/items/{$item['id']}");
        $current = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame(strlen('v3'), $current['size']);
    }

    public function testOverwritingAnItemThatIsNotAFileIsRejected(): void
    {
        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'a note', 'keepForever' => true]),
        );
        $note = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->overwriteFileItem($note['id'], 'irrelevant', 'irrelevant.txt');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);
    }
}
