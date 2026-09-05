<?php

declare(strict_types = 1);

namespace App\Tests\Controller\AdminController;

use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;
use function json_encode;

class TechnicalBreakTest extends WebTest
{
    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->databaseMockManager->createUser(new UserTestDTO('technical-break-admin@example.com', 'zaq12wsx', ['ROLE_ADMIN']));
        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('technical-break-user@example.com', 'zaq12wsx'));
    }

    private function authAsAdmin(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->admin));
    }

    private function authAsUser(): void
    {
        $this->setAuthCookie($this->databaseMockManager->loginUser($this->user));
    }

    public function testEveryEndpointRequiresAdmin(): void
    {
        $this->authAsUser();

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/technical-break');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/admin/technical-break', content: json_encode([]));
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->webClient->request(method: Request::METHOD_DELETE, uri: '/api/admin/technical-break');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/technical-break');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testStatusIsInactiveByDefault(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/technical-break');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $status = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertFalse($status['active']);
        self::assertNull($status['message']);
        self::assertNull($status['since']);
    }

    public function testEnableThenDisableUpdatesStatusAndRecordsAuditLog(): void
    {
        $this->authAsAdmin();

        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/admin/technical-break', content: json_encode(['message' => 'Wracamy o 20:00']));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $status = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertTrue($status['active']);
        self::assertSame('Wracamy o 20:00', $status['message']);
        self::assertNotNull($status['since']);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/audit-log?resourceType=technical_break&action=enable');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $entries = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertNotEmpty($entries);
        self::assertSame('technical-break-admin@example.com', $entries[0]['userEmail']);

        $this->webClient->request(method: Request::METHOD_DELETE, uri: '/api/admin/technical-break');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/technical-break');
        $status = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertFalse($status['active']);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/audit-log?resourceType=technical_break&action=disable');
        $entries = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertNotEmpty($entries);
    }

    public function testDisablingWhenNoneActiveIsANoOpWithNoAuditEntry(): void
    {
        $this->authAsAdmin();

        $this->webClient->request(method: Request::METHOD_DELETE, uri: '/api/admin/technical-break');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/audit-log?resourceType=technical_break&action=disable');
        $entries = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertEmpty($entries);
    }

    public function testActiveBreakBlocksALoggedInUserButNeverAnAdmin(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/admin/technical-break', content: json_encode(['message' => 'Wracamy o 20:00']));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        // The admin who enabled it stays logged in — every admin action still works.
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/technical-break');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/whoami');
        self::assertResponseIsSuccessful();

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/whoami');
        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        $this->responseTool->testTechnicalBreakRequestResponseData($this->webClient);
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame('Wracamy o 20:00', $body['detail']);

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_DELETE, uri: '/api/admin/technical-break');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->authAsUser();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/whoami');
        self::assertResponseIsSuccessful();
    }

    public function testAnAnonymousRequestIsNeverBlockedByAnActiveBreak(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/admin/technical-break', content: json_encode([]));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/whoami');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
