<?php

declare(strict_types=1);

namespace App\Tests\Controller\TestController;

use App\Security\CookieService;
use App\Tests\WebTest;
use App\Tests\DTO\UserTestDTO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TestControllerTest extends WebTest
{
    public function testTestCorrect(): void
    {
        $userDTO = new UserTestDTO(
            'mosinskidamian12@gmail.com',
            'zaq12wsx'
        );
        $user = $this->databaseMockManager->createUser($userDTO);

        $content = [
            'email' => $userDTO->getEmail(),
        ];

        $authCookie = $this->databaseMockManager->loginUser($user);

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/test',
            server: [
                CookieService::ACCESS_TOKEN => $authCookie->getValue(),
            ],
            content: json_encode($content)
        );

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);
    }

    public function testTestUnauthorized(): void
    {
        $userDTO = new UserTestDTO(
            'mosinskidamian12@gmail.com',
            'zaq12wsx'
        );
        $this->databaseMockManager->createUser($userDTO);

        $content = [
            'email' => $userDTO->getEmail(),
        ];

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/test',
            content: json_encode($content)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->responseTool->testUnauthorizedRequestResponseData($this->webClient);
    }

    public function testTestEmptyRequest(): void
    {
        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/test');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->responseTool->testBadRequestResponseData($this->webClient);
    }
}
