<?php

declare(strict_types = 1);

namespace App\Tests\Controller\ItemController;

use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Post-review fix: GET /api/items used to run COUNT/OFFSET/LIMIT blind to
 * Part 7 locks, filtering locked items out only after the page (and $total)
 * were already fixed — AccessKeyGuard::lockedCategoryIds()/
 * lockedItemIdsWithOwnKey() now compute what's locked *before* the query
 * runs (ItemController::list() -> ItemRepository::findFilteredPage()).
 */
class ItemListPaginationTest extends WebTest
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('pagination-user@example.com', 'zaq12wsx'));
    }

    private function auth(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function createNote(int $categoryId, string $content): int
    {
        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $categoryId, 'content' => $content, 'keepForever' => true]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $item = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        return $item['id'];
    }

    private function lockCategory(int $categoryId): void
    {
        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: "/api/categories/{$categoryId}/access-key",
            content: json_encode(['key' => 'sekret123']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pageSize: int}
     */
    private function list(string $queryString = ''): array
    {
        $this->auth();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items' . $queryString);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    /**
     * The core regression: the two *newest* items are both locked (so they
     * sort first, DESC by createdAt) — with a pageSize of 2, the old
     * (post-fetch-filtered) behaviour returned an empty page 1 even though
     * two perfectly visible items sat on page 2.
     */
    public function testPageOneIsNotEmptyWhenTheNewestItemsAreLocked(): void
    {
        $open = $this->databaseMockManager->createCategory('Open');
        $locked = $this->databaseMockManager->createCategory('Locked');

        $u1 = $this->createNote($open->getId(), 'U1');
        $u2 = $this->createNote($open->getId(), 'U2');
        // Created after (and so newer than) U1/U2 — sorts first in the
        // default createdAt DESC order.
        $this->createNote($locked->getId(), 'L1');
        $this->createNote($locked->getId(), 'L2');

        $this->lockCategory($locked->getId());

        $body = $this->list('?pageSize=2&page=1');

        $ids = array_column($body['items'], 'id');
        self::assertCount(2, $body['items']);
        self::assertContains($u1, $ids);
        self::assertContains($u2, $ids);
    }

    /**
     * $total must reflect only what this request can actually see — not the
     * raw row count including locked items it has no grant for.
     */
    public function testTotalCountsOnlyVisibleItems(): void
    {
        $open = $this->databaseMockManager->createCategory('Open');
        $locked = $this->databaseMockManager->createCategory('Locked');

        $this->createNote($open->getId(), 'U1');
        $this->createNote($open->getId(), 'U2');
        $this->createNote($locked->getId(), 'L1');
        $this->createNote($locked->getId(), 'L2');

        $this->lockCategory($locked->getId());

        $body = $this->list();

        self::assertSame(2, $body['total']);
    }

    /**
     * A grant for the locked category makes its items visible (and countable)
     * again — the exclusion is per-request, not a standing DB fact.
     */
    public function testUnlockedGrantMakesLockedItemsVisibleAndCountedAgain(): void
    {
        $locked = $this->databaseMockManager->createCategory('Locked');
        $this->createNote($locked->getId(), 'L1');
        $this->lockCategory($locked->getId());

        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: "/api/categories/{$locked->getId()}/unlock",
            content: json_encode(['key' => 'sekret123']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $grant = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_GET,
            uri: '/api/items',
            server: ['HTTP_X_POUCH_ACCESS_GRANTS' => json_encode([$grant])],
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        self::assertSame(1, $body['total']);
    }

    /**
     * An item locked by its *own* key (independent of any category lock) is
     * excluded from the count/page the same way — lockedItemIdsWithOwnKey(),
     * not just lockedCategoryIds().
     */
    public function testItemsLockedByTheirOwnKeyAreExcludedToo(): void
    {
        $category = $this->databaseMockManager->createCategory('Open');
        $u1 = $this->createNote($category->getId(), 'U1');
        $lockedItemId = $this->createNote($category->getId(), 'L1');

        $this->auth();
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: "/api/items/{$lockedItemId}/access-key",
            content: json_encode(['key' => 'sekret123']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $body = $this->list();

        self::assertSame(1, $body['total']);
        self::assertSame([$u1], array_column($body['items'], 'id'));
    }
}
