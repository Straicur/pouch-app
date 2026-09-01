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

class ItemDownloadTest extends WebTest
{
    private const string CONTENT = 'the quick brown fox jumps over the lazy dog';

    private User $user;

    private Category $category;

    private array $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('download-user@example.com', 'zaq12wsx'));
        $this->category = $this->databaseMockManager->createCategory('Downloads');

        $path = tempnam(sys_get_temp_dir(), 'pouch-download-test-');
        file_put_contents($path, self::CONTENT);
        $uploadedFile = new UploadedFile($path, 'fox.txt', 'text/plain', null, true);

        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->category->getId()],
            files: ['file' => $uploadedFile],
        );
        $this->item = json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    private function authAsUser(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    private function requestDownloadLink(): array
    {
        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_POST, uri: sprintf('/api/items/%d/download-link', $this->item['id']));

        return json_decode((string) $this->webClient->getResponse()->getContent(), true);
    }

    public function testDownloadLinkStreamsBackOriginalContent(): void
    {
        $link = $this->requestDownloadLink();

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertArrayHasKey('url', $link);

        $path = parse_url($link['url'], PHP_URL_PATH);
        $query = [];
        parse_str((string) parse_url($link['url'], PHP_URL_QUERY), $query);

        // No auth cookie here on purpose — the signed link must work standalone.
        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(method: Request::METHOD_GET, uri: $path . '?' . http_build_query($query));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        // StreamedResponse::getContent() always returns false (content isn't
        // buffered) — the BrowserKit-level response is the one that actually
        // captured the streamed bytes (via HttpKernelBrowser::filterResponse(),
        // which already calls sendContent() once internally).
        self::assertSame(self::CONTENT, $this->webClient->getInternalResponse()->getContent());
        // Guessed server-side from the actual bytes (symfony/mime), not trusted
        // from the client — hence the charset suffix for plain text.
        self::assertSame('text/plain; charset=UTF-8', $this->webClient->getResponse()->headers->get('Content-Type'));
    }

    public function testDownloadWithTamperedSignatureIsForbidden(): void
    {
        $link = $this->requestDownloadLink();
        $path = parse_url($link['url'], PHP_URL_PATH);
        $query = [];
        parse_str((string) parse_url($link['url'], PHP_URL_QUERY), $query);
        $query['signature'] = 'not-the-real-signature';

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(method: Request::METHOD_GET, uri: $path . '?' . http_build_query($query));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->responseTool->testForbiddenRequestResponseData($this->webClient);
    }

    public function testDownloadWithTamperedExpiresIsForbidden(): void
    {
        // Changing `expires` without recomputing the HMAC breaks the signature —
        // see SignedUrlServiceTest for a direct test of expiry rejection itself.
        $link = $this->requestDownloadLink();
        $path = parse_url($link['url'], PHP_URL_PATH);
        $query = [];
        parse_str((string) parse_url($link['url'], PHP_URL_QUERY), $query);
        $query['expires'] = (string) (time() - 10);

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(method: Request::METHOD_GET, uri: $path . '?' . http_build_query($query));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->responseTool->testForbiddenRequestResponseData($this->webClient);
    }

    /**
     * Część 18 point 4 — the signature is computed over "item-download:{id}",
     * so a valid (expires, signature) pair minted for one item must not
     * authorize a download of a *different* item just because the URL's id
     * segment gets swapped — the signature simply won't match the resource
     * string the new id produces.
     */
    public function testSwappingTheIdInAValidDownloadLinkIsForbidden(): void
    {
        $link = $this->requestDownloadLink();
        $query = [];
        parse_str((string) parse_url($link['url'], PHP_URL_QUERY), $query);

        $otherPath = tempnam(sys_get_temp_dir(), 'pouch-download-test-other-');
        file_put_contents($otherPath, 'different content');
        $this->authAsUser();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/items/files',
            parameters: ['categoryId' => (string) $this->category->getId()],
            files: ['file' => new UploadedFile($otherPath, 'other.txt', 'text/plain', null, true)],
        );
        $otherItem = json_decode((string) $this->webClient->getResponse()->getContent(), true);

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(
            method: Request::METHOD_GET,
            uri: sprintf('/api/items/%d/download?%s', $otherItem['id'], http_build_query($query)),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->responseTool->testForbiddenRequestResponseData($this->webClient);
    }

    public function testDownloadLinkGenerationRequiresAuth(): void
    {
        // setUp() authenticated to upload the fixture item — clear that first.
        $this->webClient->getCookieJar()->clear();

        $this->webClient->request(method: Request::METHOD_POST, uri: sprintf('/api/items/%d/download-link', $this->item['id']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
