<?php

declare(strict_types = 1);

namespace App\Tests\Controller\WhoAmIController;

use App\Security\CookieService;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WhoAmIControllerTest extends WebTest
{
    public function testWhoAmIReturnsCurrentUserEmail(): void
    {
        $userDTO = new UserTestDTO('whoami@example.com', 'zaq12wsx');
        $user = $this->databaseMockManager->createUser($userDTO);
        $authCookie = $this->databaseMockManager->loginUser($user);

        $this->webClient->request(
            method: Request::METHOD_GET,
            uri: '/api/whoami',
            server: [
                CookieService::ACCESS_TOKEN => $authCookie->getValue(),
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            (string) json_encode(['email' => $userDTO->getEmail()]),
            (string) $this->webClient->getResponse()->getContent(),
        );
    }

    public function testWhoAmIWithoutSessionReturnsUnauthorized(): void
    {
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/whoami');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->responseTool->testUnauthorizedRequestResponseData($this->webClient);
    }
}
