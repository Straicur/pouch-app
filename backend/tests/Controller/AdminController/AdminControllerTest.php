<?php

declare(strict_types = 1);

namespace App\Tests\Controller\AdminController;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\User;
use App\Enum\ItemProcessingStatus;
use App\Enum\ItemType;
use App\Repository\ItemRepository;
use App\Storage\StorageService;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use ZipArchive;

use function file_put_contents;
use function fwrite;
use function json_decode;
use function rewind;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Part 10. Covers every /api/admin/* endpoint: admin-only, and each one's
 * own behaviour. GC/expiry tests build items directly (like
 * ItemGarbageCollectorTest) since HTTP alone can't backdate an item's
 * expiresAt/trashedAt.
 */
class AdminControllerTest extends WebTest
{
    private User $admin;

    private User $user;

    private Category $category;

    private ItemRepository $itemRepository;

    private StorageService $storageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->databaseMockManager->createUser(new UserTestDTO('admin-panel-admin@example.com', 'zaq12wsx', ['ROLE_ADMIN']));
        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('admin-panel-user@example.com', 'zaq12wsx'));
        $this->category = $this->databaseMockManager->createCategory('Admin panel tests');
        $this->itemRepository = $this->getService(ItemRepository::class);
        $this->storageService = $this->getService(StorageService::class);
    }

    private function authAsAdmin(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->admin));
    }

    private function authAsUser(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function createStoredItem(string $content, ?DateTimeImmutable $expiresAt, bool $keepForever = false): Item
    {
        $storageKey = 'tests/admin/' . Uuid::v4();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $this->storageService->upload($storageKey, $stream);
        fclose($stream);

        $item = new Item(
            category: $this->category,
            type: ItemType::FILE,
            name: 'admin-test',
            keepForever: $keepForever,
            expiresAt: $expiresAt,
            processingStatus: ItemProcessingStatus::COMPLETED,
        );
        $item->setFileData(
            originalFilename: 'admin-test.txt',
            mimeType: 'text/plain',
            size: strlen($content),
            storageKey: $storageKey,
            contentHash: hash('sha256', $content),
        );
        $this->itemRepository->save($item);

        return $item;
    }

    public function testEveryEndpointRequiresAdmin(): void
    {
        $this->authAsUser();

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/storage');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/admin/gc/run');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/audit-log');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/items/expiring-soon');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/backup');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/storage');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testStorageReportReflectsUploadedFileAndDefaultLimits(): void
    {
        $this->createStoredItem('twelve bytes', null);

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/storage');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $report = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $fileUsage = null;
        foreach ($report['byType'] as $row) {
            if ('file' === $row['type']) {
                $fileUsage = $row;
            }
        }
        self::assertNotNull($fileUsage);
        self::assertGreaterThanOrEqual(1, $fileUsage['itemCount']);
        self::assertGreaterThanOrEqual(strlen('twelve bytes'), $fileUsage['totalBytes']);

        $limitTypes = array_column($report['limits'], 'type');
        self::assertContains('file', $limitTypes);
        self::assertContains('photo', $limitTypes);
    }

    public function testSettingAStorageLimitIsEnforcedOnTheNextUpload(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: '/api/admin/storage/limits/file',
            content: json_encode(['maxSizeBytes' => 5]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $path = tempnam(sys_get_temp_dir(), 'pouch-admin-test-');
        file_put_contents($path, 'this is way more than five bytes');

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->category->getId()],
            files: ['file' => new UploadedFile($path, 'big.txt', 'text/plain', null, true)],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/storage');
        $report = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        $fileLimit = null;
        foreach ($report['limits'] as $limit) {
            if ('file' === $limit['type']) {
                $fileLimit = $limit;
            }
        }
        self::assertSame(5, $fileLimit['maxSizeBytes']);
    }

    public function testSettingAStorageLimitForANonFileTypeIsRejected(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: '/api/admin/storage/limits/note',
            content: json_encode(['maxSizeBytes' => 100]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);
    }

    public function testRunGcNowRecordsARunAndItAppearsInTheHistory(): void
    {
        $now = new DateTimeImmutable();
        $this->createStoredItem('overdue', $now->sub(new DateInterval('P1D')));

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/admin/gc/run');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $run = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        self::assertSame('manual', $run['trigger']);
        self::assertGreaterThanOrEqual(1, $run['expiredCount']);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/gc/runs');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $runs = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        self::assertNotEmpty($runs);
        self::assertSame($run['id'], $runs[0]['id']);
    }

    public function testAuditLogRecordsAnItemView(): void
    {
        $item = $this->createStoredItem('viewed content', null);

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: "/api/items/{$item->getId()}");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/audit-log?resourceType=item&action=view');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $entries = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $matching = array_values(array_filter($entries, static fn (array $e): bool => $e['resourceId'] === $item->getId()));
        self::assertNotEmpty($matching);
        self::assertSame('view', $matching[0]['action']);
        self::assertSame('admin-panel-user@example.com', $matching[0]['userEmail']);
        self::assertNotNull($matching[0]['ip']);
    }

    public function testExpiringSoonListsAnItemAndExtendKeepsItOut(): void
    {
        $now = new DateTimeImmutable();
        $item = $this->createStoredItem('expiring soon', $now->add(new DateInterval('PT2H')));

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/items/expiring-soon?withinHours=24');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $expiring = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        $ids = array_column($expiring, 'id');
        self::assertContains($item->getId(), $ids);

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/admin/items/extend',
            content: json_encode(['itemIds' => [$item->getId()], 'keepForever' => true]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $extended = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertTrue($extended[0]['keepForever']);
        self::assertNull($extended[0]['expiresAt']);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/items/expiring-soon?withinHours=24');
        $stillExpiring = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertNotContains($item->getId(), array_column($stillExpiring, 'id'));
    }

    public function testBackupContainsEveryRootCategory(): void
    {
        $this->createStoredItem('backup me', null);

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/backup');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('application/zip', $this->webClient->getResponse()->headers->get('Content-Type'));

        $zipPath = tempnam(sys_get_temp_dir(), 'pouch-admin-backup-test-');
        file_put_contents($zipPath, (string) $this->webClient->getInternalResponse()->getContent());
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath));

        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($zipPath);

        self::assertContains('Admin panel tests/', $names);
        self::assertContains('Admin panel tests/admin-test.txt', $names);
    }
}
