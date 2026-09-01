<?php

declare(strict_types = 1);

namespace App\Tests\Controller\UserController;

use App\Entity\User;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;
use function json_encode;

/**
 * Covers every /api/admin/users and /api/admin/pouches endpoint — admin-only
 * account/pouch management. No self-registration exists anywhere in this
 * app, so account creation is exercised only through here.
 */
class UserControllerTest extends WebTest
{
    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->databaseMockManager->createUser(new UserTestDTO('user-mgmt-admin@example.com', 'zaq12wsx', ['ROLE_ADMIN']));
        $this->user = $this->databaseMockManager->createUser(new UserTestDTO('user-mgmt-user@example.com', 'zaq12wsx'));
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

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/users');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/pouches');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/users');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreatingAUserWithANewPouchNameReturnsAWorkingTemporaryPassword(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/admin/users',
            content: json_encode(['email' => 'new-user@example.com', 'role' => 'ROLE_USER', 'newPouchName' => 'Nowy pouch']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame('new-user@example.com', $body['user']['email']);
        self::assertSame('ROLE_USER', $body['user']['role']);
        self::assertSame('Nowy pouch', $body['user']['pouchName']);
        self::assertTrue($body['user']['enabled']);
        self::assertNotEmpty($body['temporaryPassword']);

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/login',
            content: json_encode(['email' => 'new-user@example.com', 'password' => $body['temporaryPassword']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testCreatingAUserWithAnExistingPouchIdAssignsToThatPouch(): void
    {
        $this->authAsAdmin();
        $existingPouchId = $this->user->getPouch()->getId();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/admin/users',
            content: json_encode(['email' => 'joins-existing@example.com', 'role' => 'ROLE_GUEST', 'pouchId' => $existingPouchId]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame($existingPouchId, $body['user']['pouchId']);
        self::assertSame('ROLE_GUEST', $body['user']['role']);
    }

    public function testCreatingAUserWithADuplicateEmailIsRejected(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/admin/users',
            content: json_encode(['email' => $this->user->getEmail(), 'role' => 'ROLE_USER', 'newPouchName' => 'Another pouch']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testCreatingAUserRequiresExactlyOnePouchChoice(): void
    {
        $this->authAsAdmin();

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/admin/users',
            content: json_encode(['email' => 'neither@example.com', 'role' => 'ROLE_USER']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/admin/users',
            content: json_encode([
                'email'        => 'both@example.com',
                'role'         => 'ROLE_USER',
                'pouchId'      => $this->user->getPouch()->getId(),
                'newPouchName' => 'Also new',
            ]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testChangingRoleUpdatesIt(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/admin/users/%d/role', $this->user->getId()),
            content: json_encode(['role' => 'ROLE_ADMIN']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame('ROLE_ADMIN', $body['role']);
    }

    public function testDisablingAnAccountPreventsFurtherLogin(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/admin/users/%d/enabled', $this->user->getId()),
            content: json_encode(['enabled' => false]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertFalse($body['enabled']);

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/login',
            content: json_encode(['email' => $this->user->getEmail(), 'password' => 'zaq12wsx']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * AppUserChecker, not AuthService's own check on /api/login — a token
     * minted *before* the account was disabled must stop working on its
     * very next request, not just block a fresh login attempt.
     */
    public function testDisablingAnAccountInvalidatesItsAlreadyIssuedToken(): void
    {
        $userCookie = $this->databaseMockManager->loginUser($this->user);

        $this->authAsAdmin();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/admin/users/%d/enabled', $this->user->getId()),
            content: json_encode(['enabled' => false]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->webClient->getCookieJar()->clear();
        $this->setAuthCookie($userCookie);
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/whoami');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAnAdminCannotDisableTheirOwnAccount(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(
            method: Request::METHOD_PATCH,
            uri: sprintf('/api/admin/users/%d/enabled', $this->admin->getId()),
            content: json_encode(['enabled' => false]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testResettingAPasswordReturnsANewWorkingOne(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_POST, uri: sprintf('/api/admin/users/%d/reset-password', $this->user->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertNotEmpty($body['temporaryPassword']);

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/login',
            content: json_encode(['email' => $this->user->getEmail(), 'password' => $body['temporaryPassword']]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->webClient->getCookieJar()->clear();
        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/login',
            content: json_encode(['email' => $this->user->getEmail(), 'password' => 'zaq12wsx']),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testDeletingAnAccountRemovesItButNotItsPouch(): void
    {
        $pouchId = $this->user->getPouch()->getId();

        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/admin/users/%d', $this->user->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/pouches');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $pouches = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        $ids = array_column($pouches, 'id');
        self::assertContains($pouchId, $ids);
    }

    public function testAnAdminCannotDeleteTheirOwnAccount(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_DELETE, uri: sprintf('/api/admin/users/%d', $this->admin->getId()));
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testPouchesOverviewReportsCounts(): void
    {
        $this->authAsAdmin();
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/admin/pouches');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $pouches = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        $userPouch = null;
        foreach ($pouches as $pouch) {
            if ($pouch['id'] === $this->user->getPouch()->getId()) {
                $userPouch = $pouch;
            }
        }

        self::assertNotNull($userPouch);
        self::assertGreaterThanOrEqual(1, $userPouch['userCount']);
    }
}
