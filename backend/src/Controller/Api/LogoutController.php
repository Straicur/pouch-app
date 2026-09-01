<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\ControllerHelper\Traits\BuildsLogoutResponseTrait;
use App\Security\CookieServiceInterface;
use App\Security\LogoutServiceInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Auth')]
final class LogoutController extends AbstractController
{
    use BuildsLogoutResponseTrait;

    public function __construct(
        private readonly CookieServiceInterface $cookieService,
        private readonly LogoutServiceInterface $logoutService,
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
    public function logout(Request $request): Response
    {
        $this->logoutService->logout($request);

        return $this->logoutResponse(status: Response::HTTP_OK);
    }
}
