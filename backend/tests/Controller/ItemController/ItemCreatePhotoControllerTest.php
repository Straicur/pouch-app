<?php

declare(strict_types = 1);

namespace App\Tests\Controller\ItemController;

use App\Entity\Category;
use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Photo processing has no network dependency (unlike URL scraping) — sync://
 * transport in test env means these assertions on the fully-processed result
 * are safe/deterministic.
 */
class ItemCreatePhotoControllerTest extends WebTest
{
    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('photo-item-user@example.com', 'zaq12wsx'));
        $this->category = $this->databaseMockManager->createCategory('Photos');
    }

    private function authAsUser(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function createTestPhoto(string $text): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pouch-photo-controller-test-') . '.png';
        $image = imagecreatetruecolor(400, 120);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 20, 40, $text, imagecolorallocate($image, 0, 0, 0));
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'photo.png', 'image/png', null, true);
    }

    public function testUploadPhotoGeneratesThumbnailAndOcrText(): void
    {
        $this->authAsUser();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/photos',
            parameters: ['categoryId' => (string) $this->category->getId()],
            files: ['file' => $this->createTestPhoto('POUCH')],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $item = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        self::assertSame('photo', $item['type']);
        self::assertSame('completed', $item['processingStatus']);
        self::assertTrue($item['hasThumbnail']);
        self::assertStringContainsStringIgnoringCase('POUCH', (string) $item['extractedText']);
    }

    public function testUploadPhotoRejectsNonImageExtension(): void
    {
        $this->authAsUser();

        $path = tempnam(sys_get_temp_dir(), 'pouch-photo-controller-test-') . '.txt';
        file_put_contents($path, 'not an image');

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/photos',
            parameters: ['categoryId' => (string) $this->category->getId()],
            files: ['file' => new UploadedFile($path, 'not-a-photo.txt', 'text/plain', null, true)],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);
    }

    // A disallowed extension is caught first regardless of MIME (see the
    // test above) — this one keeps a valid extension so the MIME check
    // itself, not the extension one, is what has to reject it.
    public function testUploadPhotoRejectsMismatchedMimeType(): void
    {
        $this->authAsUser();

        $path = tempnam(sys_get_temp_dir(), 'pouch-photo-controller-test-') . '.png';
        file_put_contents($path, 'not actually a png');

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/photos',
            parameters: ['categoryId' => (string) $this->category->getId()],
            files: ['file' => new UploadedFile($path, 'fake.png', 'text/plain', null, true)],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->responseTool->testBadRequestResponseData($this->webClient);
    }

    public function testThumbnailLinkStreamsBackAJpeg(): void
    {
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/photos',
            parameters: ['categoryId' => (string) $this->category->getId()],
            files: ['file' => $this->createTestPhoto('THUMB')],
        );
        $item = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_POST, uri: sprintf('/api/items/%d/thumbnail-link', $item['id']));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $link = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $path = parse_url($link['url'], PHP_URL_PATH);
        $query = [];
        parse_str((string) parse_url($link['url'], PHP_URL_QUERY), $query);

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(method: Request::METHOD_GET, uri: $path . '?' . http_build_query($query));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('image/jpeg', $this->webClient->getResponse()->headers->get('Content-Type'));

        $bytes = $this->webClient->getInternalResponse()->getContent();
        self::assertNotFalse(getimagesizefromstring($bytes));
    }
}
