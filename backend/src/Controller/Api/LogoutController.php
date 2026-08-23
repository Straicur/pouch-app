<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\Security\CookieService;
use App\Security\CookieServiceInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[OA\Tag(name: 'Auth')]
final class LogoutController extends AbstractController
{
    public function __construct(
        private readonly CookieServiceInterface $cookieService,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    #[Route('/api/logout', name: 'logout', methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Logout endpoint',
        requestBody: new OA\RequestBody(),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
            ),
        ]
    )]
    public function logout(
        Request $request,
    ): Response {
        $session = $request->getSession();
        if ($session->isStarted()) {
            $session->invalidate();
        }

        $this->tokenStorage->setToken(null);

        $response = new Response();

        $response->headers->setCookie(
            $this->cookieService->prepareLogoutCookie(CookieService::ACCESS_TOKEN)
        );

        $response->headers->setCookie(
            $this->cookieService->prepareLogoutCookie(CookieService::REFRESH_TOKEN)
        );

        $this->tokenStorage->setToken(null);

        return $response;
    }
}
