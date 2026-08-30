<?php

declare(strict_types=1);

namespace App\Tests\Controller\LogoutController;

use App\Security\CookieService;
use App\Tests\WebTest;
use App\Tests\DTO\UserTestDTO;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;
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

    // A stolen refresh token must stop working the moment its owner logs
    // out — otherwise it stays valid at /api/refresh for its full TTL.
    public function testLogoutRevokesRefreshTokenRecord(): void
    {
        $userDTO = new UserTestDTO(
            'mosinskidamian13@gmail.com',
            'zaq12wsx'
        );
        $user = $this->databaseMockManager->createUser($userDTO);
        $authCookie = $this->databaseMockManager->loginUser($user);
        $refreshToken = $this->databaseMockManager->createRefreshToken($user);

        $refreshTokenManager = self::getContainer()->get(RefreshTokenManagerInterface::class);
        self::assertNotNull($refreshTokenManager->get($refreshToken));

        // The `server: [...]` one-off trick (used in testLogoutCorrect above)
        // doesn't populate $request->cookies — it only reaches the request
        // as a server var, which happens to be enough for the ACCESS_TOKEN
        // case above since /api/logout doesn't actually gate on it. Revoking
        // the *right* refresh token depends on the controller really reading
        // it from the cookie bag, so this test puts both in the cookie jar
        // for real.
        $this->setAuthCookie($authCookie);
        $this->webClient->getCookieJar()->set(new BrowserKitCookie(CookieService::REFRESH_TOKEN, $refreshToken));

        $this->webClient->request(
            method: Request::METHOD_POST,
            uri: '/api/logout',
        );

        self::assertResponseStatusCodeSame(200);
        self::assertNull($refreshTokenManager->get($refreshToken));
    }
}
