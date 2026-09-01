<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\ControllerHelper\Traits\AuthorizesRequestsTrait;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedExceptionModel;
use App\Security\AuthorizationServiceInterface;
use App\Security\CookieService;
use App\Security\CookieServiceInterface;
use App\Security\TokenServiceInterface;
use App\Services\Pouch\PouchDeletionServiceInterface;
use App\Services\User\UserServiceInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Self-service account management — every action here operates on the
 * *caller's own* account, never one looked up by id (unlike UserController,
 * which is entirely ROLE_ADMIN-only and always acts on someone else's).
 */
#[OA\Response(response: 401, description: 'User not authorized', content: new Model(type: UnauthorizedExceptionModel::class))]
#[OA\Response(response: 403, description: 'Forbidden', content: new Model(type: ForbiddenExceptionModel::class))]
#[OA\Tag(name: 'Account')]
final class AccountController extends AbstractController
{
    use AuthorizesRequestsTrait;

    public function __construct(
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly UserServiceInterface $userService,
        private readonly PouchDeletionServiceInterface $pouchDeletionService,
        private readonly CookieServiceInterface $cookieService,
        private readonly TokenServiceInterface $tokenService,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException   a ROLE_ADMIN account tried this — see .../account/pouch instead
     */
    #[Route('/api/account', name: 'account_delete', methods: [Request::METHOD_DELETE])]
    #[OA\Delete(
        description: 'Delete the caller\'s own account — the pouch it belongs to (and everything in it) is '
            . 'untouched, a User is just a login. Refused for a ROLE_ADMIN account (see DELETE .../account/pouch '
            . 'instead) — logs the caller out on success.',
        responses: [new OA\Response(response: 204, description: 'Deleted, caller logged out')],
    )]
    #[OA\Response(response: 400, description: 'Caller is a ROLE_ADMIN account', content: new Model(type: BadRequestExceptionModel::class))]
    public function delete(Request $request): Response
    {
        $user = $this->assertGranted('ROLE_GUEST');

        $this->userService->deleteOwnAccount($user->getId());

        return $this->loggedOutResponse($request);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws ConflictException     another account still belongs to this pouch
     * @throws BadRequestException   caller is the only ROLE_ADMIN account system-wide
     */
    #[Route('/api/account/pouch', name: 'account_delete_pouch', methods: [Request::METHOD_DELETE])]
    #[OA\Delete(
        description: 'An admin\'s self-service "usuń cały pouch" — deletes the caller\'s pouch and everything in '
            . 'it (every category, every item and its storage objects, every account in the pouch including the '
            . "caller's own). Refused if any other account still belongs to this pouch (remove/reassign them "
            . 'first, admin panel) or if the caller is the only ROLE_ADMIN account left system-wide. Logs the '
            . 'caller out on success.',
        responses: [new OA\Response(response: 204, description: 'Deleted, caller logged out')],
    )]
    #[OA\Response(response: 409, description: 'Another account still belongs to this pouch', content: new Model(type: ConflictExceptionModel::class))]
    #[OA\Response(response: 400, description: 'Caller is the only ROLE_ADMIN account system-wide', content: new Model(type: BadRequestExceptionModel::class))]
    public function deletePouch(Request $request): Response
    {
        $user = $this->assertGranted('ROLE_ADMIN');

        $this->pouchDeletionService->deleteOwnPouch($user->getId());

        return $this->loggedOutResponse($request);
    }

    /**
     * Same cookie/token/session cleanup as LogoutController — the account
     * this session belongs to no longer exists, so the session shouldn't
     * either.
     */
    private function loggedOutResponse(Request $request): Response
    {
        $session = $request->getSession();
        if ($session->isStarted()) {
            $session->invalidate();
        }

        $refreshToken = $request->cookies->get(CookieService::REFRESH_TOKEN);
        if (null !== $refreshToken) {
            $this->tokenService->revokeRefreshToken($refreshToken);
        }

        $this->tokenStorage->setToken(null);

        $response = new Response(status: Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookieService->prepareLogoutCookie(CookieService::ACCESS_TOKEN));
        $response->headers->setCookie($this->cookieService->prepareLogoutCookie(CookieService::REFRESH_TOKEN));

        return $response;
    }
}
