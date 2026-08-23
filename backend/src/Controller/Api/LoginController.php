<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\DTO\Request\LoginRequestDTO;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException\TooManyRequestsException;
use App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException\TooManyRequestsExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException\UnprocessableContentException;
use App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException\UnprocessableContentExceptionModel;
use App\Security\AuthServiceInterface;
use App\Security\ConfigServiceInterface;
use App\Security\CookieService;
use App\Security\CookieServiceInterface;
use App\Security\LoginRateLimiterInterface;
use App\Security\TokenServiceInterface;
use App\Service\RequestServiceInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Response(
    response: 400,
    description: 'JSON Data Invalid',
    content: new Model(type: BadRequestExceptionModel::class)
)]
#[OA\Response(
    response: 401,
    description: 'User not authorized',
    content: new Model(type: UnauthorizedExceptionModel::class)
)]
#[OA\Response(
    response: 422,
    description: 'Unprocessable Content',
    content: new Model(type: UnprocessableContentExceptionModel::class)
)]
#[OA\Response(
    response: 429,
    description: 'Too Many Requests',
    content: new Model(type: TooManyRequestsExceptionModel::class)
)]
#[OA\Tag(name: 'Auth')]
final class LoginController extends AbstractController
{
    public function __construct(
        private readonly RequestServiceInterface $requestService,
        private readonly CookieServiceInterface $cookieService,
        private readonly AuthServiceInterface $authService,
        private readonly TokenServiceInterface $tokenService,
        private readonly ConfigServiceInterface $configService,
        private readonly LoginRateLimiterInterface $loginRateLimiter,
    ) {}

    /**
     * @throws BadRequestException
     * @throws UnauthorizedException
     * @throws UnprocessableContentException
     * @throws TooManyRequestsException
     */
    #[Route('/api/login', name: 'login', methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Login endpoint',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: new Model(type: LoginRequestDTO::class),
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
            ),
        ]
    )]
    public function login(
        Request $request,
    ): Response {
        $this->loginRateLimiter->consume($request);

        $loginRequestDTO = $this->requestService->getRequestBodyContent($request, LoginRequestDTO::class);

        $user = $this->authService->getUserByEmailAndPassword(
            email: $loginRequestDTO->getEmail(),
            password: $loginRequestDTO->getPassword()
        );

        $response = new Response();

        $token = $this->tokenService->createToken($user);
        $response->headers->setCookie(
            $this->cookieService->prepareAuthCookie(
                name: CookieService::ACCESS_TOKEN,
                token: $token,
                expire: $this->configService->getAccessTokenTimeToLive(),
            )
        );

        $refreshToken = $this->tokenService->createRefreshToken($user);
        $response->headers->setCookie(
            $this->cookieService->prepareAuthCookie(
                name: CookieService::REFRESH_TOKEN,
                token: $refreshToken->getRefreshToken(),
                expire: $this->configService->getRefreshTokenTimeToLive(),
            )
        );

        return $response;
    }
}
