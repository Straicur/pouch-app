<?php

declare(strict_types = 1);

namespace App\Tests\Controller\ItemController;

use App\Entity\Category;
use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `async` transport is `sync://` in test env (see messenger.yaml), so
 * ScrapeUrlMessageHandler runs inline with the REAL http_client here — no
 * mocked HTTP (see ScrapeUrlMessageHandlerTest/OpenGraphScraperTest for that).
 * Assertions below are deliberately limited to what the request itself
 * guarantees regardless of network conditions.
 */
class ItemCreateUrlControllerTest extends WebTest
{
    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('url-item-user@example.com', 'zaq12wsx'));
        $this->category = $this->databaseMockManager->createCategory('URLs');
    }

    private function authAsUser(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    public function testCreateUrlItemReturnsItImmediatelyProcessed(): void
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/urls',
            content: json_encode(['categoryId' => $this->category->getId(), 'url' => 'https://example.com/']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $item = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        self::assertSame('url', $item['type']);
        self::assertSame('https://example.com/', $item['url']);
        self::assertSame($this->category->getId(), $item['categoryId']);
        // sync:// transport means the handler already ran by the time we get a
        // response — one way or another it's no longer "pending".
        self::assertContains($item['processingStatus'], ['completed', 'failed']);
    }

    public function testCreateUrlItemWithMalformedUrlReturnsUnprocessable(): void
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/urls',
            content: json_encode(['categoryId' => $this->category->getId(), 'url' => 'not-a-url']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->responseTool->testUnprocessableContentRequestResponseData($this->webClient);
    }

    public function testCreateUrlItemWithMissingCategoryReturnsNotFound(): void
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/urls',
            content: json_encode(['categoryId' => 999999, 'url' => 'https://example.com/']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->responseTool->testNotFoundRequestResponseData($this->webClient);
    }

    public function testGuestCannotCreateUrlItem(): void
    {
        $guest = $this->databaseMockManager->createUser(new UserTestDTO('url-item-guest@example.com', 'zaq12wsx', ['ROLE_GUEST']));
        $this->setAuthCookie($this->databaseMockManager->loginUser($guest));

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/urls',
            content: json_encode(['categoryId' => $this->category->getId(), 'url' => 'https://example.com/']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->responseTool->testForbiddenRequestResponseData($this->webClient);
    }
}
