<?php

declare(strict_types=1);

namespace App\Tests\Controller\AccessKeyController;

use App\Entity\User;
use App\Security\AccessKey\AccessKeyGuard;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Part 7. The rate limiter itself (429 after N wrong attempts) is covered
 * separately by AccessKeyRateLimiterTest — the "access_key" limiter is bumped
 * to 1000/15min in when@test (see rate_limiter.yaml), same as "login", so it
 * can't realistically be exercised through this webClient.
 */
class AccessKeyControllerTest extends WebTest
{
    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('access-key-user@example.com', 'zaq12wsx'));
        $this->admin = $this->databaseMockManager->createUser(new UserTestDTO('access-key-admin@example.com', 'zaq12wsx', ['ROLE_ADMIN']));
    }

    private function auth(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function authAsAdmin(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->admin));
    }

    private function setCategoryKey(int $categoryId, string $key): void
    {
        $this->auth();

        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: "/api/categories/{$categoryId}/access-key",
            content: json_encode(['key' => $key]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array{resource: string, expires: int, signature: string}
     */
    private function unlockCategory(int $categoryId, string $key): array
    {
        $this->auth();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: "/api/categories/{$categoryId}/unlock",
            content: json_encode(['key' => $key]),
        );

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    private function createNote(int $categoryId, array $grants = []): void
    {
        $this->auth();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            server: $this->grantsHeader($grants),
            content: json_encode(['categoryId' => $categoryId, 'content' => 'secret note', 'keepForever' => true]),
        );
    }

    private function getItem(int $itemId, array $grants = []): void
    {
        $this->auth();

        $this->webClient->request(
            method: Request::METHOD_GET,
            uri: "/api/items/{$itemId}",
            server: $this->grantsHeader($grants),
        );
    }

    private function grantsHeader(array $grants): array
    {
        if ([] === $grants) {
            return [];
        }

        return ['HTTP_' . str_replace('-', '_', mb_strtoupper(AccessKeyGuard::GRANTS_HEADER)) => json_encode([$grants])];
    }

    /**
     * Part 10: "Reset klucza dostępu" — a regular user can't change/remove a
     * key they don't know, but an admin can, on purpose.
     */
    public function testChangingAnExistingKeyRequiresProvingItUnlessAdmin(): void
    {
        $category = $this->databaseMockManager->createCategory('Reset test');
        $this->setCategoryKey($category->getId(), 'sekret123');

        // A regular user (even the one who *set* the key — this endpoint has
        // no notion of "owner") without a grant is refused.
        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: "/api/categories/{$category->getId()}/access-key",
            content: json_encode(['key' => 'new-key']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // An admin bypasses that requirement entirely.
        $this->authAsAdmin();
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: "/api/categories/{$category->getId()}/access-key",
            content: json_encode(['key' => null]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // The reset actually took — the old key no longer unlocks it.
        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: "/api/categories/{$category->getId()}/unlock",
            content: json_encode(['key' => 'sekret123']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testSetKeyThenCreatingItemWithoutUnlockingIsForbidden(): void
    {
        $category = $this->databaseMockManager->createCategory('Locked');
        $this->setCategoryKey($category->getId(), 'sekret123');

        $this->createNote($category->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->responseTool->testForbiddenRequestResponseData($this->webClient);
    }

    public function testUnlockWithWrongKeyReturnsUnauthorized(): void
    {
        $category = $this->databaseMockManager->createCategory('Locked');
        $this->setCategoryKey($category->getId(), 'sekret123');

        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: "/api/categories/{$category->getId()}/unlock",
            content: json_encode(['key' => 'wrong-key']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->responseTool->testUnauthorizedRequestResponseData($this->webClient);
    }

    public function testUnlockingWithCorrectKeyGrantsAccess(): void
    {
        $category = $this->databaseMockManager->createCategory('Locked');
        $this->setCategoryKey($category->getId(), 'sekret123');

        $grant = $this->unlockCategory($category->getId(), 'sekret123');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('category-key:' . $category->getId(), $grant['resource']);

        $this->createNote($category->getId(), $grant);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $item = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        // Without the grant on this later request, the same item is 403 again
        // — nothing was remembered server-side (stateless firewall).
        $this->getItem($item['id']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->getItem($item['id'], $grant);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * A regression test for a bug the manual test caught: an item with no key
     * of its own, sitting in a locked category, used to be reported as
     * "item.locked" — misleading, since unlocking the item (which has no key)
     * is impossible; the category is what actually needs unlocking.
     */
    public function testGettingAnUnkeyedItemInALockedCategoryReportsTheCategoryAsLocked(): void
    {
        $category = $this->databaseMockManager->createCategory('Locked');
        $this->createNote($category->getId());
        $item = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->setCategoryKey($category->getId(), 'sekret123');

        $this->getItem($item['id']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $content = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertStringContainsString('kategori', $content['detail']);
    }

    public function testSubcategoryInheritsParentKeyAndParentGrantUnlocksIt(): void
    {
        $parent = $this->databaseMockManager->createCategory('Parent');
        $this->setCategoryKey($parent->getId(), 'sekret123');
        $child = $this->databaseMockManager->createCategory('Child', $parent);

        $grant = $this->unlockCategory($parent->getId(), 'sekret123');

        $this->createNote($child->getId(), $grant);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testItemOwnKeyIsIndependentOfUnprotectedCategory(): void
    {
        $category = $this->databaseMockManager->createCategory('Open');
        $this->createNote($category->getId());
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $item = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: "/api/items/{$item['id']}/access-key",
            content: json_encode(['key' => 'item-secret']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->getItem($item['id']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: "/api/items/{$item['id']}/unlock",
            content: json_encode(['key' => 'item-secret']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $grant = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->getItem($item['id'], $grant);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
