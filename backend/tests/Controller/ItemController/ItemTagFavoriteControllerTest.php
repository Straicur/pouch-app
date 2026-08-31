<?php

declare(strict_types = 1);

namespace App\Tests\Controller\ItemController;

use App\Entity\Category;
use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ItemTagFavoriteControllerTest extends WebTest
{
    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('tag-favorite-user@example.com', 'zaq12wsx'));
        $this->category = $this->databaseMockManager->createCategory('Tagged');
    }

    private function authAsUser(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function createNote(string $content): array
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => $content]),
        );

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    public function testNewItemHasNoTagsAndIsNotFavorite(): void
    {
        $item = $this->createNote('plain note');

        self::assertSame([], $item['tags']);
        self::assertFalse($item['favorite']);
    }

    public function testReplaceTagsAssignsThemAndNormalizesCase(): void
    {
        $item = $this->createNote('note to tag');

        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $item['id']),
            content: json_encode(['tags' => ['Work', 'URGENT', 'work']]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $updated = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        // "Work" and "work" collapse into one tag.
        self::assertCount(2, $updated['tags']);
        self::assertContains('work', $updated['tags']);
        self::assertContains('urgent', $updated['tags']);
    }

    public function testReplaceTagsFullyReplacesThePreviousSet(): void
    {
        $item = $this->createNote('re-tag me');

        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $item['id']),
            content: json_encode(['tags' => ['first']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $item['id']),
            content: json_encode(['tags' => ['second']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $updated = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        self::assertSame(['second'], $updated['tags']);
    }

    public function testTooManyTagsIsRejected(): void
    {
        $item = $this->createNote('overtagged');

        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $item['id']),
            content: json_encode(['tags' => array_map(static fn (int $i): string => "tag-{$i}", range(1, 21))]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // A non-string element ({"tags":[123]}) has to fail validation (422), not
    // reach TagService::resolveTags() and blow up on trim(123) under
    // strict_types (500).
    public function testNonStringTagElementIsRejectedNotA500(): void
    {
        $item = $this->createNote('bad tag type');

        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $item['id']),
            content: json_encode(['tags' => [123]]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testTagListEndpointReturnsAssignedTagNames(): void
    {
        $item = $this->createNote('for the tag list');
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $item['id']),
            content: json_encode(['tags' => ['alpha', 'beta']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/tags');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $tags = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertContains('alpha', $tags);
        self::assertContains('beta', $tags);
    }

    // A tag that lost its last item shouldn't keep haunting the autocomplete
    // list — see TagRepository::findAllOrderedByName().
    public function testTagListEndpointOmitsTagsNoLongerUsedByAnyItem(): void
    {
        $item = $this->createNote('orphaned tag source');
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $item['id']),
            content: json_encode(['tags' => ['soon-orphaned']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $item['id']),
            content: json_encode(['tags' => []]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/tags');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $tags = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertNotContains('soon-orphaned', $tags);
    }

    public function testMarkAndUnmarkFavorite(): void
    {
        $item = $this->createNote('favorite me');

        $this->webClient->request(method: Request::METHOD_PUT, uri: sprintf('/api/items/%d/favorite', $item['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $marked = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertTrue($marked['favorite']);

        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/items/%d/favorite', $item['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $unmarked = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertFalse($unmarked['favorite']);
    }

    public function testListCanBeFilteredToFavoritesOnly(): void
    {
        $favorite = $this->createNote('favorite note');
        $this->createNote('not a favorite');

        $this->webClient->request(method: Request::METHOD_PUT, uri: sprintf('/api/items/%d/favorite', $favorite['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items?favorite=1');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        $items = $body['items'];
        $ids = array_column($items, 'id');
        self::assertContains($favorite['id'], $ids);
        self::assertCount(1, $items);
    }

    public function testListCanBeFilteredByTag(): void
    {
        $tagged = $this->createNote('has a tag');
        $this->createNote('has no tag');

        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $tagged['id']),
            content: json_encode(['tags' => ['important']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items?tags=important');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        $items = $body['items'];
        self::assertCount(1, $items);
        self::assertSame($tagged['id'], $items[0]['id']);
    }

    public function testGuestCannotTagOrFavorite(): void
    {
        $item = $this->createNote('guest cannot touch this');

        $guest = $this->databaseMockManager->createUser(new UserTestDTO('search-guest@example.com', 'zaq12wsx', ['ROLE_GUEST']));
        $this->setAuthCookie($this->databaseMockManager->loginUser($guest));

        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $item['id']),
            content: json_encode(['tags' => ['nope']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->webClient->request(method: Request::METHOD_PUT, uri: sprintf('/api/items/%d/favorite', $item['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
