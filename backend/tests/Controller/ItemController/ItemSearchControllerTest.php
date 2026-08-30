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

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
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

        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/items?q=searchword&categoryId=%d', $otherCategory->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([], json_decode((string) $this->webClient->getResponse()->getContent(), true));

        $this->webClient->request(method: Request::METHOD_GET, uri: sprintf('/api/items?q=searchword&categoryId=%d', $this->category->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $items = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertCount(1, $items);
        self::assertSame($item['id'], $items[0]['id']);
    }
}
