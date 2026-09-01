<?php

declare(strict_types = 1);

namespace App\Tests\Controller\ItemController;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\User;
use App\Enum\ItemProcessingStatus;
use App\Enum\ItemType;
use App\Repository\ItemRepository;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One test per channel `q` has to reach — name, tag, note content, OCR text,
 * OpenGraph title/description — each isolated with a unique keyword so a
 * false positive from another item/field would be obvious. The OpenGraph
 * item is built directly through the repository (not POST /api/items/urls)
 * to avoid depending on the real network — see ItemCreateUrlControllerTest.
 */
class ItemSearchControllerTest extends WebTest
{
    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('search-user@example.com', 'zaq12wsx'));
        $this->category = $this->databaseMockManager->createCategory('Searchable');
    }

    private function authAsUser(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function search(string $query): array
    {
        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items?q=' . urlencode($query));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        return $body['items'];
    }

    public function testSearchMatchesByName(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'irrelevant content', 'name' => 'Zebrasearchword']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $items = $this->search('zebrasearchword');
        self::assertCount(1, $items);
        self::assertSame('Zebrasearchword', $items[0]['name']);
    }

    public function testSearchMatchesByNamePrefix(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'irrelevant content', 'name' => 'Giraffeprefixword']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Only the first few letters — search-as-you-type shouldn't need the
        // whole word (see ItemRepository::buildPrefixTsQuery()).
        $items = $this->search('giraf');
        self::assertCount(1, $items);
        self::assertSame('Giraffeprefixword', $items[0]['name']);
    }

    public function testSearchRanksNameMatchAboveNoteContentMatch(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'irrelevant content', 'name' => 'Rankingword']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $nameMatch = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        // Explicit, unrelated $name — without it ItemService::deriveNoteName()
        // would derive the name from $content, and the match would score via
        // the (weight A) name field too, defeating the point of this test.
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'name' => 'Unrelated title', 'content' => 'a note mentioning rankingword in passing']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $noteMatch = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $items = $this->search('rankingword');
        self::assertCount(2, $items);
        self::assertSame($nameMatch['id'], $items[0]['id']);
        self::assertSame($noteMatch['id'], $items[1]['id']);
    }

    public function testSearchMatchesByTag(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'tagged note']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $item = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $item['id']),
            content: json_encode(['tags' => ['giraffetagword']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $items = $this->search('giraffetagword');
        self::assertCount(1, $items);
        self::assertSame($item['id'], $items[0]['id']);
    }

    // "%" in a search term must match a literal "%", not act as an ILIKE
    // wildcard — otherwise searching for a tag literally named "50%off"
    // would also match an unrelated "50xoff" tag (any single character where
    // the "%" sits). See ItemRepository::escapeLikeWildcards().
    public function testSearchTreatsPercentInQueryAsALiteralCharacter(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'literal percent item']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $literalItem = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $literalItem['id']),
            content: json_encode(['tags' => ['50%off']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'unrelated wildcard-shaped item']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $wildcardShapedItem = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $wildcardShapedItem['id']),
            content: json_encode(['tags' => ['50xoff']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $items = $this->search('50%off');
        $ids = array_column($items, 'id');
        self::assertContains($literalItem['id'], $ids);
        self::assertNotContains($wildcardShapedItem['id'], $ids);
    }

    public function testSearchMatchesByNoteContent(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'a note about elephantsearchword and other things']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $items = $this->search('elephantsearchword');
        self::assertCount(1, $items);
    }

    public function testSearchMatchesByOcrText(): void
    {
        $this->authAsUser();

        $path = tempnam(sys_get_temp_dir(), 'pouch-search-photo-') . '.png';
        $image = imagecreatetruecolor(400, 120);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 20, 40, 'POUCHOCRMARKER', imagecolorallocate($image, 0, 0, 0));
        imagepng($image, $path);
        imagedestroy($image);

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/photos',
            parameters: ['categoryId' => (string) $this->category->getId()],
            files: ['file' => new UploadedFile($path, 'ocr.png', 'image/png', null, true)],
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $items = $this->search('pouchocrmarker');
        self::assertCount(1, $items);
    }

    public function testSearchMatchesByOpenGraphTitleAndDescription(): void
    {
        /** @var ItemRepository $itemRepository */
        $itemRepository = $this->getService(ItemRepository::class);

        $item = new Item(
            category: $this->category,
            type: ItemType::URL,
            name: 'Some link',
            keepForever: false,
            expiresAt: new DateTimeImmutable('+1 day'),
            processingStatus: ItemProcessingStatus::COMPLETED,
        );
        $item->setUrl('https://example.com/pandasearchword');
        $item->setPageMetadata('Pandasearchword the title', 'A description mentioning koalasearchword too');
        $itemRepository->save($item);

        self::assertCount(1, $this->search('pandasearchword'));
        self::assertCount(1, $this->search('koalasearchword'));
    }

    /**
     * Część 17: item.url is now part of search_vector (weight D) too — a
     * bare domain/URL fragment matches even when the scraped title/
     * description never happens to repeat it.
     */
    public function testSearchMatchesByUrlWhenTitleAndDescriptionDoNotContainTheQuery(): void
    {
        /** @var ItemRepository $itemRepository */
        $itemRepository = $this->getService(ItemRepository::class);

        $item = new Item(
            category: $this->category,
            type: ItemType::URL,
            name: 'Some link',
            keepForever: false,
            expiresAt: new DateTimeImmutable('+1 day'),
            processingStatus: ItemProcessingStatus::COMPLETED,
        );
        $item->setUrl('https://urlsearchhost.example/some-path');
        $item->setPageMetadata('Completely unrelated title', 'Completely unrelated description');
        $itemRepository->save($item);

        self::assertCount(1, $this->search('urlsearchhost'));
    }

    /**
     * Część 17: a search result carries a highlighted excerpt of the
     * matched text — wrapped in ItemRepository::SNIPPET_HIGHLIGHT_START/END
     * sentinels, not HTML (ItemCard renders it as plain text segments; see
     * that constant's own doc comment for why raw HTML here would be a
     * stored-XSS hole).
     */
    public function testSearchResultCarriesAHighlightedSnippetOfTheMatch(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'a note about snippetmarkerword right here']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $items = $this->search('snippetmarkerword');
        self::assertCount(1, $items);
        self::assertNotNull($items[0]['snippet']);
        self::assertStringContainsString(
            ItemRepository::SNIPPET_HIGHLIGHT_START . 'snippetmarkerword' . ItemRepository::SNIPPET_HIGHLIGHT_END,
            $items[0]['snippet'],
        );
        self::assertStringNotContainsString('<b>', $items[0]['snippet']);
    }

    /**
     * Część 17: a misspelled query that the exact prefix search can't match
     * at all still finds the item via pg_trgm similarity — the fallback
     * only kicks in once the exact search comes back empty (see
     * ItemRepository::searchMatchingIds()).
     */
    public function testSearchToleratesATypoViaTrigramFallback(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'irrelevant content', 'name' => 'Typosearchterm']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // One letter swapped ("typpsearchterm") — to_tsquery's prefix match
        // fails outright on this, only trigram similarity can still find it.
        $items = $this->search('typpsearchterm');
        self::assertCount(1, $items);
        self::assertSame('Typosearchterm', $items[0]['name']);
    }

    /**
     * A plain (non-search) list carries no snippet at all — it's only
     * computed when $filter->query is actually set (ItemController::list()).
     */
    public function testListWithoutASearchQueryCarriesNoSnippet(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'plain listing content']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertNull($body['items'][0]['snippet']);
    }

    public function testSearchCombinesWithCategoryAndFavoriteFilters(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'multi-filter searchword note']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $item = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $otherCategory = $this->databaseMockManager->createCategory('Other');

        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/items?q=searchword&categoryIds=%d', $otherCategory->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $emptyBody = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame([], $emptyBody['items']);

        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/items?q=searchword&categoryIds=%d', $this->category->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        $items = $body['items'];
        self::assertCount(1, $items);
        self::assertSame($item['id'], $items[0]['id']);
    }

    /**
     * Code-review regression: an exact match in *another* pouch used to make
     * searchMatchingIds() return a non-empty list globally, which suppressed
     * the typo-tolerant fallback for a pouch that had no match of its own at
     * all — pouch A's typo search came back empty even though pouch A had a
     * plausible fuzzy match, purely because pouch B happened to have an
     * exact one for a similar word. searchMatchingIds()/searchMatchingIdsFuzzy()
     * now take $pouchId explicitly instead of relying on the later DQL
     * requery (PouchFilter) to catch it after the fact.
     */
    public function testExactMatchInAnotherPouchDoesNotSuppressThisPouchsFuzzyFallback(): void
    {
        $pouchA = $this->databaseMockManager->createPouch('Fuzzy pouch A');
        $pouchB = $this->databaseMockManager->createPouch('Fuzzy pouch B');
        $userA = $this->databaseMockManager->createUser(new UserTestDTO('fuzzy-pouch-a@example.com', 'zaq12wsx'), $pouchA);
        $userB = $this->databaseMockManager->createUser(new UserTestDTO('fuzzy-pouch-b@example.com', 'zaq12wsx'), $pouchB);
        $categoryA = $this->databaseMockManager->createCategory('Fuzzy category A', pouch: $pouchA);
        $categoryB = $this->databaseMockManager->createCategory('Fuzzy category B', pouch: $pouchB);

        // Pouch B: an exact match for the un-misspelled word.
        $this->setAuthCookie($this->databaseMockManager->loginUser($userB));
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $categoryB->getId(), 'content' => 'irrelevant', 'name' => 'Crossfuzzyword']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Pouch A: only a misspelled version — no exact match here at all.
        $this->setAuthCookie($this->databaseMockManager->loginUser($userA));
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $categoryA->getId(), 'content' => 'irrelevant', 'name' => 'Crossfuzzywrd']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->setAuthCookie($this->databaseMockManager->loginUser($userA));
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/items?q=crossfuzzyword');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        self::assertCount(1, $body['items']);
        self::assertSame('Crossfuzzywrd', $body['items'][0]['name']);
    }

    /**
     * Code-review regression: findSnippets() used to run ts_headline() for
     * every id it was given regardless of whether search_vector actually
     * contained $query — a tag-only match (or a fuzzy match, which by
     * definition doesn't satisfy the exact tsquery either) got an arbitrary,
     * unhighlighted excerpt from the start of the document, which the
     * frontend then displayed as if it were a real match explanation.
     */
    public function testATagOnlyMatchCarriesNoSnippet(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'nothing relevant in the body here']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $item = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->webClient->request(
            method: Request::METHOD_PUT,
            uri: sprintf('/api/items/%d/tags', $item['id']),
            content: json_encode(['tags' => ['tagonlysnippetmarker']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $items = $this->search('tagonlysnippetmarker');
        self::assertCount(1, $items);
        self::assertNull($items[0]['snippet']);
    }

    /**
     * Same regression as testATagOnlyMatchCarriesNoSnippet(), for the other
     * source of a non-text match: search_vector doesn't contain the typo'd
     * query either — that's exactly why the fuzzy fallback ran at all.
     */
    public function testAFuzzyMatchCarriesNoSnippet(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/notes',
            content: json_encode(['categoryId' => $this->category->getId(), 'content' => 'irrelevant content', 'name' => 'Fuzzysnippetterm']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $items = $this->search('fuzzysnippetterrm');
        self::assertCount(1, $items);
        self::assertSame('Fuzzysnippetterm', $items[0]['name']);
        self::assertNull($items[0]['snippet']);
    }
}
