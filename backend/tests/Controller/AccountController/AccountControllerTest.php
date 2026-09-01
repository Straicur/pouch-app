<?php

declare(strict_types = 1);

namespace App\Tests\Controller\AccountController;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Pouch;
use App\Entity\User;
use App\Enum\ItemProcessingStatus;
use App\Enum\ItemType;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Repository\PouchRepository;
use App\Repository\UserRepository;
use App\Services\Storage\StorageService;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function bin2hex;
use function fclose;
use function fopen;
use function fwrite;
use function hash;
use function json_encode;
use function random_bytes;
use function rewind;
use function strlen;

/**
 * Self-service account deletion (Część 16, "RODO/eksport i usunięcie danych
 * użytkownika" — narrowed to just account deletion, per user's own scoping):
 * DELETE /api/account for a regular account (login gone, pouch/data
 * untouched) and DELETE /api/account/pouch for an admin (the whole pouch,
 * with everything in it, gone).
 */
class AccountControllerTest extends WebTest
{
    private UserRepository $userRepository;

    private CategoryRepository $categoryRepository;

    private ItemRepository $itemRepository;

    private PouchRepository $pouchRepository;

    private StorageService $storageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = $this->getService(UserRepository::class);
        $this->categoryRepository = $this->getService(CategoryRepository::class);
        $this->itemRepository = $this->getService(ItemRepository::class);
        $this->pouchRepository = $this->getService(PouchRepository::class);
        $this->storageService = $this->getService(StorageService::class);
    }

    private function authAs(User $user): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($user));
    }

    private function createStoredItem(Category $category, string $content): Item
    {
        $storageKey = 'tests/account-deletion/' . bin2hex(random_bytes(8));

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $this->storageService->upload($storageKey, $stream);
        fclose($stream);

        $item = new Item(
            category: $category,
            type: ItemType::FILE,
            name: 'account-deletion-test',
            keepForever: true,
            expiresAt: null,
            processingStatus: ItemProcessingStatus::COMPLETED,
        );
        $item->setFileData(
            originalFilename: 'account-deletion-test.txt',
            mimeType: 'text/plain',
            size: strlen($content),
            storageKey: $storageKey,
            contentHash: hash('sha256', $content),
        );
        $this->itemRepository->save($item);

        return $item;
    }

    public function testDeletingOwnAccountRemovesLoginButKeepsThePouchAndItsData(): void
    {
        $pouch = $this->databaseMockManager->createPouch('Self-delete pouch');
        $user = $this->databaseMockManager->createUser(new UserTestDTO('self-delete@example.com', 'zaq12wsx'), $pouch);
        $category = $this->databaseMockManager->createCategory('Self-delete category', pouch: $pouch);
        $this->createStoredItem($category, 'kept after self-delete');

        $this->authAs($user);
        $this->webClient->request(method: Request::METHOD_DELETE, uri: '/api/account');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Same cookies the request itself carried — the delete response's
        // own logout cookies (empty value, past expiry) already apply here.
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/whoami');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/login',
            content: json_encode(['email' => 'self-delete@example.com', 'password' => 'zaq12wsx']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        self::assertNull($this->userRepository->findUserByEmail('self-delete@example.com'));
        self::assertNotNull($this->pouchRepository->find($pouch->getId()));
        self::assertNotNull($this->categoryRepository->find($category->getId()));
        self::assertCount(1, $this->itemRepository->findAllInPouch($pouch->getId()));
    }

    public function testAnAdminCannotUseTheRegularSelfDeleteEndpoint(): void
    {
        $admin = $this->databaseMockManager->createUser(new UserTestDTO('self-delete-admin@example.com', 'zaq12wsx', ['ROLE_ADMIN']));

        $this->authAs($admin);
        $this->webClient->request(method: Request::METHOD_DELETE, uri: '/api/account');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertNotNull($this->userRepository->find($admin->getId()));
    }

    public function testAdminDeletingTheirOwnPouchRemovesEverythingInItAndTheirStorage(): void
    {
        $pouch = $this->databaseMockManager->createPouch('Solo admin pouch');
        $admin = $this->databaseMockManager->createUser(new UserTestDTO('solo-admin@example.com', 'zaq12wsx', ['ROLE_ADMIN']), $pouch);
        $category = $this->databaseMockManager->createCategory('Solo admin category', pouch: $pouch);
        $item = $this->createStoredItem($category, 'goes away with the pouch');
        $storageKey = (string) $item->getStorageKey();
        self::assertTrue($this->storageService->exists($storageKey));

        // Captured up front: the request below removes all four rows
        // server-side, and re-reading an id off an already-removed entity
        // afterward isn't reliable (same reason ItemGarbageCollector::
        // purgeTrash() captures $itemId before its own remove()+flush()).
        $pouchId = $pouch->getId();
        $categoryId = $category->getId();
        $itemId = $item->getId();
        $adminId = $admin->getId();

        $this->authAs($admin);
        $this->webClient->request(method: Request::METHOD_DELETE, uri: '/api/account/pouch');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::assertNull($this->pouchRepository->find($pouchId));
        self::assertNull($this->categoryRepository->find($categoryId));
        self::assertNull($this->itemRepository->find($itemId));
        self::assertNull($this->userRepository->find($adminId));
        self::assertFalse($this->storageService->exists($storageKey));

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/whoami');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAdminCannotDeleteTheirPouchWhileAnotherAccountBelongsToIt(): void
    {
        $pouch = $this->databaseMockManager->createPouch('Shared pouch');
        $admin = $this->databaseMockManager->createUser(new UserTestDTO('shared-admin@example.com', 'zaq12wsx', ['ROLE_ADMIN']), $pouch);
        $this->databaseMockManager->createUser(new UserTestDTO('shared-user@example.com', 'zaq12wsx'), $pouch);

        $this->authAs($admin);
        $this->webClient->request(method: Request::METHOD_DELETE, uri: '/api/account/pouch');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertNotNull($this->pouchRepository->find($pouch->getId()));
    }

    /**
     * The "last admin" guard only blocks when it would strand *someone
     * else* — a genuinely solo admin (own pouch is the whole system, see
     * testAdminDeletingTheirOwnPouchRemovesEverythingInItAndTheirStorage)
     * is free to wipe everything, admin role included. This test's other
     * pouch/user is what makes the block apply here.
     */
    public function testTheOnlyAdminSystemWideCannotDeleteTheirPouchWhileOtherAccountsExistElsewhere(): void
    {
        $this->databaseMockManager->createUser(new UserTestDTO('unrelated-user@example.com', 'zaq12wsx'));

        $pouch = $this->databaseMockManager->createPouch('Last admin pouch');
        $admin = $this->databaseMockManager->createUser(new UserTestDTO('last-admin@example.com', 'zaq12wsx', ['ROLE_ADMIN']), $pouch);

        $this->authAs($admin);
        $this->webClient->request(method: Request::METHOD_DELETE, uri: '/api/account/pouch');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertNotNull($this->pouchRepository->find($pouch->getId()));
    }
}
