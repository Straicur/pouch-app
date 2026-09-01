<?php

declare(strict_types = 1);

namespace App\Controller\Api\Admin;

use App\ControllerHelper\Traits\AuthorizesRequestsTrait;
use App\DTO\Mapper\UserMapper;
use App\DTO\Request\UserCreateRequestDTO;
use App\DTO\Request\UserEnabledUpdateRequestDTO;
use App\DTO\Request\UserRoleUpdateRequestDTO;
use App\DTO\Response\PouchOverviewResponseDTO;
use App\DTO\Response\UserCreatedResponseDTO;
use App\DTO\Response\UserResponseDTO;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictException;
use App\ExceptionManagement\Exceptions\ApiException\ConflictException\ConflictExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException\UnprocessableContentException;
use App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException\UnprocessableContentExceptionModel;
use App\Security\AuthorizationServiceInterface;
use App\Services\Audit\AuditLoggerInterface;
use App\Services\Pouch\PouchOverviewServiceInterface;
use App\Services\Request\RequestServiceInterface;
use App\Services\User\UserServiceInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Admin-only account/pouch management — flat ROLE_ADMIN check via
 * assertAdmin(), same pattern as AdminController: nothing here is ever
 * "yours" vs. "someone else's" the way CategoryVoter/ItemVoter's
 * per-resource nuance is. There is no self-registration endpoint anywhere in
 * this app — an account only ever comes into existence through POST here.
 */
#[OA\Response(response: 401, description: 'User not authorized', content: new Model(type: UnauthorizedExceptionModel::class))]
#[OA\Response(response: 403, description: 'Forbidden — admin only', content: new Model(type: ForbiddenExceptionModel::class))]
#[OA\Tag(name: 'User')]
final class UserController extends AbstractController
{
    use AuthorizesRequestsTrait;

    public function __construct(
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly RequestServiceInterface $requestService,
        private readonly UserServiceInterface $userService,
        private readonly PouchOverviewServiceInterface $pouchOverviewService,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly SerializerInterface $serializer,
    ) {}

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/admin/users', name: 'admin_user_list', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'List every account, across every pouch',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: UserResponseDTO::class))),
            ),
        ]
    )]
    public function list(): Response
    {
        $this->assertAdmin();

        $responseDTO = UserMapper::toResponseDTOList($this->userService->list());

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws ConflictException             $email is already taken
     * @throws NotFoundException             pouchId is given but doesn't exist
     * @throws BadRequestException           neither or both of pouchId/newPouchName were given
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/admin/users', name: 'admin_user_create', methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Create an account — the only way one comes into existence (no self-registration). Assign it '
            . 'to an existing pouch (pouchId) or found a new one for it (newPouchName), exactly one of the two. '
            . 'Returns a server-generated temporary password once — there is no email/notification mechanism in '
            . 'this app, so it must be communicated to the new user out of band.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: UserCreateRequestDTO::class), type: 'object')),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new Model(type: UserCreatedResponseDTO::class)),
        ]
    )]
    #[OA\Response(response: 404, description: "pouchId is given but doesn't exist", content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 409, description: 'That email is already taken', content: new Model(type: ConflictExceptionModel::class))]
    #[OA\Response(response: 400, description: 'Neither or both of pouchId/newPouchName were given', content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function create(Request $request): Response
    {
        $this->assertAdmin();

        $createRequestDTO = $this->requestService->getRequestBodyContent($request, UserCreateRequestDTO::class);

        $created = $this->userService->create(
            email: $createRequestDTO->getEmail(),
            role: $createRequestDTO->getRole(),
            pouchId: $createRequestDTO->getPouchId(),
            newPouchName: $createRequestDTO->getNewPouchName(),
        );

        $responseDTO = UserMapper::toCreatedResponseDTO($created);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_CREATED);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/admin/users/{id}/role', name: 'admin_user_role', requirements: ['id' => '\d+'], methods: [Request::METHOD_PATCH])]
    #[OA\Patch(
        description: "Change an account's role",
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: UserRoleUpdateRequestDTO::class), type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Success', content: new Model(type: UserResponseDTO::class))],
    )]
    #[OA\Response(response: 404, description: 'Account not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function changeRole(Request $request, int $id): Response
    {
        $this->assertAdmin();

        $roleRequestDTO = $this->requestService->getRequestBodyContent($request, UserRoleUpdateRequestDTO::class);
        $user = $this->userService->changeRole($id, $roleRequestDTO->getRole());

        $responseDTO = UserMapper::toResponseDTO($user);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException           $id is the caller's own account
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/admin/users/{id}/enabled', name: 'admin_user_enabled', requirements: ['id' => '\d+'], methods: [Request::METHOD_PATCH])]
    #[OA\Patch(
        description: 'Enable or disable (block) an account — a disabled account can no longer log in, and an '
            . 'already-issued access token stops working on its own next refresh (see AppUserChecker). An admin '
            . 'cannot disable their own account.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: UserEnabledUpdateRequestDTO::class), type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Success', content: new Model(type: UserResponseDTO::class))],
    )]
    #[OA\Response(response: 404, description: 'Account not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 400, description: "id is the caller's own account", content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function setEnabled(Request $request, int $id): Response
    {
        $currentUser = $this->assertAdmin();

        $enabledRequestDTO = $this->requestService->getRequestBodyContent($request, UserEnabledUpdateRequestDTO::class);
        $user = $this->userService->setEnabled($id, $enabledRequestDTO->isEnabled(), $currentUser->getId());

        $responseDTO = UserMapper::toResponseDTO($user);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/admin/users/{id}/reset-password', name: 'admin_user_reset_password', requirements: ['id' => '\d+'], methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: "Reset an account's password to a new server-generated temporary one, returned once — same "
            . 'out-of-band-communication caveat as account creation.',
        responses: [new OA\Response(response: 200, description: 'Success', content: new Model(type: UserCreatedResponseDTO::class))],
    )]
    #[OA\Response(response: 404, description: 'Account not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function resetPassword(int $id): Response
    {
        $this->assertAdmin();

        $reset = $this->userService->resetPassword($id);
        $responseDTO = UserMapper::toCreatedResponseDTO($reset);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException   $id is the caller's own account
     */
    #[Route('/api/admin/users/{id}', name: 'admin_user_delete', requirements: ['id' => '\d+'], methods: [Request::METHOD_DELETE])]
    #[OA\Delete(
        description: 'Permanently delete an account. The pouch it belonged to (and everything in it) is untouched '
            . '— a User is just a login, ownership of categories/items lives on the pouch, not the account. An '
            . 'admin cannot delete their own account.',
        responses: [new OA\Response(response: 204, description: 'Deleted')],
    )]
    #[OA\Response(response: 404, description: 'Account not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 400, description: "id is the caller's own account", content: new Model(type: BadRequestExceptionModel::class))]
    public function delete(Request $request, int $id): Response
    {
        $currentUser = $this->assertAdmin();

        $pouch = $this->userService->getById($id)->getPouch();
        $this->userService->delete($id, $currentUser->getId());
        $this->auditLogger->log(AuditLoggerInterface::ACTION_DELETE, AuditLoggerInterface::RESOURCE_USER, $id, $currentUser, $request, $pouch);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/admin/pouches', name: 'admin_pouch_list', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: '"Przegląd pouchy" — every pouch with how many users/categories/active items it has',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: PouchOverviewResponseDTO::class))),
            ),
        ]
    )]
    public function pouches(): Response
    {
        $this->assertAdmin();

        $responseDTO = UserMapper::toPouchOverviewResponseDTOList($this->pouchOverviewService->list());

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }
}
