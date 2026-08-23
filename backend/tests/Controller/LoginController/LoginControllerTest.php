<?php

declare(strict_types=1);

namespace App\Tests\Controller\LoginController;

use App\Security\CookieService;
use App\Tests\DTO\UserTestDTO;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginControllerTest extends WebTest
{
    public function testLoginCorrect(): void
    {
        $userDTO = new UserTestDTO(
          'mosinskidamian12@gmail.com',
                'zaq12wsx'
        );
        $this->databaseMockManager->createUser($userDTO);

        $content = [
            'email' => $userDTO->getEmail(),
            'password' => $userDTO->getPassword()
        ];

        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/login', content: json_encode($content));

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);

        $response = $this->webClient->getResponse();
        $cookies = $response->headers->getCookies();
        $this->assertCount(2, $cookies);
        $accessTokenValue = $this->getCookieValueFromJar(CookieService::ACCESS_TOKEN);
        $this->assertNotNull($accessTokenValue);
        $this->assertNotEmpty($accessTokenValue);
        $refreshTokenValue = $this->getCookieValueFromJar(CookieService::REFRESH_TOKEN);
        $this->assertNotNull($refreshTokenValue);
        $this->assertNotEmpty($refreshTokenValue);
    }

    public function testLoginIncorrectCredentials(): void
    {
        $userDTO = new UserTestDTO(
            'mosinskidamian12@gmail.com',
            'zaq12wsx'
        );

        $content = [
            'email' => $userDTO->getEmail(),
            'password' => $userDTO->getPassword()
        ];

        $this->webClient->request(method: Request::METHOD_POST, uri:'/api/login', content: json_encode($content));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->responseTool->testUnauthorizedRequestResponseData($this->webClient);
    }

    public function testLoginIncorrectEmailCredentials(): void
    {
        $userDTO = new UserTestDTO(
            'mosinskidamian12.com',
            'zaq12wsx'
        );

        $content = [
            'email' => $userDTO->getEmail(),
            'password' => $userDTO->getPassword()
        ];

        $this->webClient->request(method: Request::METHOD_POST, uri:'/api/login', content: json_encode($content));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->responseTool->testUnprocessableContentRequestResponseData($this->webClient);
    }

    public function testLoginEmptyRequest(): void
    {
        $this->webClient->request(method: Request::METHOD_POST, uri: '/api/login');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->responseTool->testBadRequestResponseData($this->webClient);
    }
}
