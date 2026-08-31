<?php

declare(strict_types = 1);

namespace App\Tests\Controller\CategoryController;

use App\Entity\Category;
use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

use function file_put_contents;
use function json_decode;
use function json_encode;
use function str_ends_with;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Part 9, second half: "Pobierz całą kategorię" as a ZIP preserving structure.
 */
class CategoryExportTest extends WebTest
{
    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('export-user@example.com', 'zaq12wsx'));
        $this->admin = $this->databaseMockManager->createUser(
            new UserTestDTO('export-admin@example.com', 'zaq12wsx', ['ROLE_ADMIN'])
        );
    }

    private function auth(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function authAsAdmin(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->admin));
    }

    private function uploadFile(int $categoryId, string $content, string $filename): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-export-test-');
        file_put_contents($path, $content);

        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $categoryId],
            files: ['file' => new UploadedFile($path, $filename, 'text/plain', null, true)],
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    private function createNote(int $categoryId, string $content): void
    {
        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $categoryId, 'content' => $content, 'keepForever' => true]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * @return array<string, string> zip entry name => contents, for every non-directory entry
     */
    private function downloadAndReadZip(int $categoryId): array
    {
        // Categories built directly via DatabaseMockManager (no HTTP request
        // in between) leave stale, never-hydrated Collection objects behind
        // in the EntityManager's identity map for Category::$children — real
        // requests always re-`find()` fresh, this only bites because a test
        // can create a whole tree without ever going through the kernel.
        $this->entityManager->clear();

        $this->auth();
        $this->webClient->request(method: Request::METHOD_GET, uri: "/api/categories/{$categoryId}/export");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('application/zip', $this->webClient->getResponse()->headers->get('Content-Type'));

        return $this->readZipEntries();
    }

    /**
     * @return array<string, string> zip entry name => contents, for every non-directory entry
     */
    private function readZipEntries(): array
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'pouch-export-test-read-');
        file_put_contents($zipPath, (string) $this->webClient->getInternalResponse()->getContent());

        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath));

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            self::assertIsString($name);
            if (str_ends_with($name, '/')) {
                continue;
            }

            $entries[$name] = (string) $zip->getFromName($name);
        }

        $zip->close();
        unlink($zipPath);

        return $entries;
    }

    public function testExportPreservesStructureAndContent(): void
    {
        $root = $this->databaseMockManager->createCategory('Docs');
        $sub = $this->databaseMockManager->createCategory('Notes', $root);

        $this->uploadFile($root->getId(), 'file content', 'report.txt');
        $this->createNote($sub->getId(), 'note content');

        $entries = $this->downloadAndReadZip($root->getId());

        self::assertArrayHasKey('Docs/report.txt', $entries);
        self::assertSame('file content', $entries['Docs/report.txt']);

        self::assertArrayHasKey('Docs/Notes/note content.md', $entries);
        self::assertSame('note content', $entries['Docs/Notes/note content.md']);
    }

    public function testExportKeepsEmptySubcategoriesAsFolders(): void
    {
        $root = $this->databaseMockManager->createCategory('Empty tree');
        $this->databaseMockManager->createCategory('Empty child', $root);

        // See downloadAndReadZip()'s comment — same identity-map staleness,
        // just inlined here since this test also wants numFiles/getNameIndex
        // access (empty-directory entries, not real file entries).
        $this->entityManager->clear();

        $this->auth();
        $this->webClient->request(method: Request::METHOD_GET, uri: "/api/categories/{$root->getId()}/export");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $zipPath = tempnam(sys_get_temp_dir(), 'pouch-export-test-read-');
        file_put_contents($zipPath, (string) $this->webClient->getInternalResponse()->getContent());
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath));

        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($zipPath);

        self::assertContains('Empty tree/', $names);
        self::assertContains('Empty tree/Empty child/', $names);
    }

    public function testExportOfLockedCategorySkipsItsItemsButKeepsUnlockedSiblings(): void
    {
        $root = $this->databaseMockManager->createCategory('Mixed');
        $locked = $this->databaseMockManager->createCategory('Locked', $root);
        $open = $this->databaseMockManager->createCategory('Open', $root);

        $this->uploadFile($locked->getId(), 'secret content', 'secret.txt');
        $this->uploadFile($open->getId(), 'public content', 'public.txt');

        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: "/api/categories/{$locked->getId()}/access-key",
            content: json_encode(['key' => 'sekret123']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // No grant supplied — the export request never unlocked it.
        $entries = $this->downloadAndReadZip($root->getId());

        self::assertArrayNotHasKey('Mixed/Locked/secret.txt', $entries);
        self::assertArrayHasKey('Mixed/Open/public.txt', $entries);
        self::assertSame('public content', $entries['Mixed/Open/public.txt']);
    }

    public function testExportGivesDistinctNamesToItemsThatWouldOtherwiseCollide(): void
    {
        $root = $this->databaseMockManager->createCategory('Collisions');
        $this->createNote($root->getId(), 'same');
        $this->createNote($root->getId(), 'same');

        $entries = $this->downloadAndReadZip($root->getId());

        self::assertArrayHasKey('Collisions/same.md', $entries);
        self::assertArrayHasKey('Collisions/same (2).md', $entries);
    }

    /**
     * Post-review fix: a plain navigation (what the frontend's
     * triggerDownload.ts actually uses, so the ZIP streams) can't set the
     * X-Pouch-Access-Grants header — the grant now has to be relayed via a
     * "grants" query parameter instead, which CategoryController::export()
     * puts back onto the header AccessKeyGuard reads.
     */
    public function testExportWithGrantViaQueryParamIncludesUnlockedContent(): void
    {
        $root = $this->databaseMockManager->createCategory('Mixed');
        $locked = $this->databaseMockManager->createCategory('Locked', $root);

        $this->uploadFile($locked->getId(), 'secret content', 'secret.txt');

        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: "/api/categories/{$locked->getId()}/access-key",
            content: json_encode(['key' => 'sekret123']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: "/api/categories/{$locked->getId()}/unlock",
            content: json_encode(['key' => 'sekret123']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $grant = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->entityManager->clear();

        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_GET,
            uri: "/api/categories/{$root->getId()}/export?grants=" . urlencode((string) json_encode([$grant])),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $entries = $this->readZipEntries();

        self::assertArrayHasKey('Mixed/Locked/secret.txt', $entries);
        self::assertSame('secret content', $entries['Mixed/Locked/secret.txt']);
    }

    /**
     * Post-review fix: the admin's full backup (same buildZipFromRoots()
     * codepath, `bypassLocks: true`) is a deliberately different action from
     * an ordinary category export — it always includes everything, with no
     * grant needed at all.
     */
    public function testAdminBackupIncludesLockedContentWithoutAnyGrant(): void
    {
        $root = $this->databaseMockManager->createCategory('Backup root');
        $locked = $this->databaseMockManager->createCategory('Locked', $root);

        $this->uploadFile($locked->getId(), 'secret content', 'secret.txt');

        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: "/api/categories/{$locked->getId()}/access-key",
            content: json_encode(['key' => 'sekret123']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->entityManager->clear();

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/backup');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $entries = $this->readZipEntries();

        self::assertArrayHasKey('Backup root/Locked/secret.txt', $entries);
        self::assertSame('secret content', $entries['Backup root/Locked/secret.txt']);
    }
}
