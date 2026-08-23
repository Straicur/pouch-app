<?php

declare(strict_types=1);

namespace App\Tests\Controller\LogoutController;

use App\Security\CookieService;
use App\Tests\WebTest;
use App\Tests\DTO\UserTestDTO;
use Symfony\Component\HttpFoundation\Request;

class LogoutControllerTest extends WebTest
{
    public function testLogoutCorrect(): void
    {
        $userDTO = new UserTestDTO(
            'mosinskidamian12@gmail.com',
            'zaq12wsx'
        );
        $user = $this->databaseMockManager->createUser($userDTO);
        $authCookie = $this->databaseMockManager->loginUser($user);

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/logout',
            server: [
                CookieService::ACCESS_TOKEN => $authCookie->getValue(),
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);

        $response = $this->webClient->getResponse();
        $cookies = $response->headers->getCookies();
        $this->assertCount(2, $cookies);
        $accessTokenValue = $this->getCookieValueFromJar(CookieService::ACCESS_TOKEN);
        $this->assertNull($accessTokenValue);
        $refreshTokenValue = $this->getCookieValueFromJar(CookieService::REFRESH_TOKEN);
        $this->assertNull($refreshTokenValue);
    }
}
