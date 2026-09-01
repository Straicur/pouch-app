<?php

declare(strict_types = 1);

namespace App\Tests\Controller\AdminController;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Pouch;
use App\Entity\User;
use App\Enum\ItemProcessingStatus;
use App\Enum\ItemType;
use App\Repository\ItemRepository;
use App\Services\Storage\StorageService;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use ZipArchive;

use function array_column;
use function fclose;
use function file_put_contents;
use function fopen;
use function fwrite;
use function hash;
use function json_decode;
use function rewind;
use function sprintf;
use function str_starts_with;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Krok 3's `?pouchId=` filter on every admin endpoint (storage/gc/audit-log/
 * expiring-soon/backup) + the new admin item browser/cross-pouch delete —
 * two pouches, an item in each, and asserting one pouch's admin view never
 * shows the other's.
 */
class AdminPouchScopingTest extends WebTest
{
    private User $admin;

    private Pouch $pouchA;

    private Pouch $pouchB;

    private Category $categoryA;

    private Category $categoryB;

    private ItemRepository $itemRepository;

    private StorageService $storageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pouchA = $this->databaseMockManager->createPouch('Admin scoping pouch A');
        $this->pouchB = $this->databaseMockManager->createPouch('Admin scoping pouch B');
        $this->admin = $this->databaseMockManager->createUser(new UserTestDTO('admin-scoping@example.com', 'zaq12wsx', ['ROLE_ADMIN']));
        $this->categoryA = $this->databaseMockManager->createCategory('Scoping A', pouch: $this->pouchA);
        $this->categoryB = $this->databaseMockManager->createCategory('Scoping B', pouch: $this->pouchB);
        $this->itemRepository = $this->getService(ItemRepository::class);
        $this->storageService = $this->getService(StorageService::class);
    }

    private function authAsAdmin(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->admin));
    }

    private function createStoredItem(Category $category, string $content, ?DateTimeImmutable $expiresAt = null, bool $keepForever = true): Item
    {
        $storageKey = 'tests/admin-scoping/' . Uuid::v4();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $this->storageService->upload($storageKey, $stream);
        fclose($stream);

        $item = new Item(
            category: $category,
            type: ItemType::FILE,
            name: 'admin-scoping-test',
            keepForever: $keepForever,
            expiresAt: $expiresAt,
            processingStatus: ItemProcessingStatus::COMPLETED,
        );
        $item->setFileData(
            originalFilename: 'admin-scoping-test.txt',
            mimeType: 'text/plain',
            size: strlen($content),
            storageKey: $storageKey,
            contentHash: hash('sha256', $content),
        );
        $this->itemRepository->save($item);

        return $item;
    }

    public function testStorageReportScopedToPouchOnlyCountsThatPouchsItems(): void
    {
        $this->createStoredItem($this->categoryA, 'aaaaaaaaaa');
        $this->createStoredItem($this->categoryB, 'bb');

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/admin/storage?pouchId=%d', $this->pouchA->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $report = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        $fileUsage = null;
        foreach ($report['byType'] as $entry) {
            if ('file' === $entry['type']) {
                $fileUsage = $entry;
            }
        }

        self::assertNotNull($fileUsage);
        self::assertSame(1, $fileUsage['itemCount']);
        self::assertSame(10, $fileUsage['totalBytes']);
    }

    public function testManualGcRunScopedToPouchLeavesTheOtherPouchsOverdueItemUntouched(): void
    {
        $past = new DateTimeImmutable('-1 hour');
        $itemA = $this->createStoredItem($this->categoryA, 'expired in A', expiresAt: $past, keepForever: false);
        $itemB = $this->createStoredItem($this->categoryB, 'expired in B', expiresAt: $past, keepForever: false);

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_POST, uri: sprintf('/api/admin/gc/run?pouchId=%d', $this->pouchA->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $result = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame(1, $result['expiredCount']);
        self::assertSame($this->pouchA->getName(), $result['pouchName']);

        $refreshedA = $this->itemRepository->find($itemA->getId());
        $refreshedB = $this->itemRepository->find($itemB->getId());
        self::assertNotNull($refreshedA);
        self::assertNotNull($refreshedB);
        self::assertTrue($refreshedA->isTrashed());
        self::assertFalse($refreshedB->isTrashed());
    }

    public function testGcRunsHistoryScopedToPouchOmitsTheCronsGlobalSweep(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/admin/gc/run');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $globalRun = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertNull($globalRun['pouchName']);

        $this->webClient->request(method: Request::METHOD_POST, uri: sprintf('/api/admin/gc/run?pouchId=%d', $this->pouchA->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/admin/gc/runs?pouchId=%d', $this->pouchA->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $runs = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        self::assertNotEmpty($runs);
        foreach ($runs as $run) {
            self::assertSame($this->pouchA->getName(), $run['pouchName']);
        }
    }

    public function testAuditLogScopedToPouchOnlyShowsThatPouchsEntries(): void
    {
        $itemA = $this->createStoredItem($this->categoryA, 'audit A');
        $itemB = $this->createStoredItem($this->categoryB, 'audit B');

        $this->authAsAdmin();
        // AdminController::deleteItem() logs ACTION_DELETE with the item's
        // own pouch — a real audit-log entry to filter, one per pouch.
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/admin/items/%d', $itemA->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/admin/items/%d', $itemB->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/admin/audit-log?pouchId=%d', $this->pouchA->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $entries = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        self::assertNotEmpty($entries);
        foreach ($entries as $entry) {
            self::assertSame($this->pouchA->getName(), $entry['pouchName']);
        }
        self::assertNotContains($itemB->getId(), array_column($entries, 'resourceId'));
    }

    public function testExpiringSoonScopedToPouchOmitsTheOtherPouchsItem(): void
    {
        $soon = new DateTimeImmutable()->add(new DateInterval('PT2H'));
        $this->createStoredItem($this->categoryA, 'expiring A', expiresAt: $soon, keepForever: false);
        $itemB = $this->createStoredItem($this->categoryB, 'expiring B', expiresAt: $soon, keepForever: false);

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/admin/items/expiring-soon?withinHours=24&pouchId=%d', $this->pouchA->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $ids = array_column(json_decode((string) $this->webClient->getResponse()->getContent(), true), 'id');
        self::assertNotContains($itemB->getId(), $ids);
    }

    public function testBackupScopedToPouchOnlyContainsThatPouchsCategory(): void
    {
        $this->createStoredItem($this->categoryA, 'backup A');
        $this->createStoredItem($this->categoryB, 'backup B');

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/admin/backup?pouchId=%d', $this->pouchA->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $zipPath = tempnam(sys_get_temp_dir(), 'admin-backup-scoping-');
        file_put_contents($zipPath, (string) $this->webClient->getInternalResponse()->getContent());

        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($zipPath);

        $hasCategoryA = false;
        $hasCategoryB = false;
        foreach ($names as $name) {
            if (str_starts_with((string) $name, $this->categoryA->getName())) {
                $hasCategoryA = true;
            }
            if (str_starts_with((string) $name, $this->categoryB->getName())) {
                $hasCategoryB = true;
            }
        }

        self::assertTrue($hasCategoryA);
        self::assertFalse($hasCategoryB);
    }

    public function testItemBrowserRequiresPouchIdAndOnlyListsThatPouchsItems(): void
    {
        $this->createStoredItem($this->categoryA, 'browse A');
        $this->createStoredItem($this->categoryB, 'browse B');

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/items');
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/admin/items?pouchId=%d', $this->pouchA->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame(1, $body['total']);
        self::assertSame($this->categoryA->getId(), $body['items'][0]['categoryId']);
    }

    public function testAdminCanDeleteAnItemInAnyPouch(): void
    {
        $itemB = $this->createStoredItem($this->categoryB, 'delete me');

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/admin/items/%d', $itemB->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $refreshed = $this->itemRepository->find($itemB->getId());
        self::assertNotNull($refreshed);
        self::assertTrue($refreshed->isTrashed());
    }
}
