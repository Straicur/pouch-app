<?php

declare(strict_types = 1);

namespace App\Controller\Api\User;

use App\ControllerHelper\Enum\UserRole;
use App\ControllerHelper\Traits\AuthorizesRequestsTrait;
use App\DTO\Mapper\AccessGrantMapper;
use App\DTO\Request\AccessKeySetRequestDTO;
use App\DTO\Request\AccessKeyUnlockRequestDTO;
use App\DTO\Response\AccessGrantResponseDTO;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException\TooManyRequestsException;
use App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException\TooManyRequestsExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException\UnprocessableContentException;
use App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException\UnprocessableContentExceptionModel;
use App\Security\AccessKey\AccessKeyGuardInterface;
use App\Security\AccessKey\AccessKeyServiceInterface;
use App\Security\AuthorizationServiceInterface;
use App\Security\Voter\CategoryVoter;
use App\Security\Voter\ItemVoter;
use App\Services\Audit\AuditLoggerInterface;
use App\Services\Category\CategoryServiceInterface;
use App\Services\Item\ItemServiceInterface;
use App\Services\Request\RequestServiceInterface;
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
 * Access keys on categories (inherited by subcategories that don't set
 * their own — see AccessKeyService::findEffectiveKeyHolder()) and,
 * independently, on individual items. Every action checks auth (401) then a
 * voter (403) same as Category/ItemController; unlock() additionally
 * rate-limits (429) and can 401 on a wrong key — see AccessKeyServiceInterface.
 * setCategoryKey()/setItemKey() require proving the *current* key (if there
 * is one) before changing/removing it, except for ROLE_ADMIN, which bypasses
 * that on purpose.
 */
#[OA\Response(response: 401, description: 'User not authorized', content: new Model(type: UnauthorizedExceptionModel::class))]
#[OA\Response(response: 403, description: "Forbidden — logged in, but role doesn't allow this action", content: new Model(type: ForbiddenExceptionModel::class))]
#[OA\Response(response: 429, description: 'Too Many Requests', content: new Model(type: TooManyRequestsExceptionModel::class))]
#[OA\Tag(name: 'AccessKey')]
final class AccessKeyController extends AbstractController
{
    use AuthorizesRequestsTrait;

    public function __construct(
        private readonly RequestServiceInterface $requestService,
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly AccessKeyServiceInterface $accessKeyService,
        private readonly CategoryServiceInterface $categoryService,
        private readonly ItemServiceInterface $itemService,
        private readonly AccessKeyGuardInterface $accessKeyGuard,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly SerializerInterface $serializer,
    ) {}

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnprocessableContentException
     */
    #[Route('/api/categories/{id}/access-key', name: 'category_access_key_set', requirements: ['id' => '\d+'], methods: [Request::METHOD_PUT])]
    #[OA\Put(
        description: "Set, change, or remove (key: null) a category's own access key",
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: AccessKeySetRequestDTO::class), type: 'object')),
        responses: [new OA\Response(response: 204, description: 'Success')],
    )]
    #[OA\Response(response: 404, description: 'Category not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function setCategoryKey(Request $request, int $id): Response
    {
        $user = $this->assertGranted(CategoryVoter::MANAGE_KEY);

        $category = $this->categoryService->getById($id);

        // Changing/removing a key that's already protecting this category
        // (own or inherited) requires proving you know it first, same as
        // actually unlocking it, unless you're ROLE_ADMIN.
        if (false === $this->isGranted(UserRole::ADMIN->value)) {
            $this->accessKeyGuard->assertCategoryUnlocked($category, $request);
        }

        $setRequestDTO = $this->requestService->getRequestBodyContent($request, AccessKeySetRequestDTO::class);
        $this->accessKeyService->setCategoryKey(categoryId: $id, key: $setRequestDTO->getKey());
        $this->auditLogger->log(AuditLoggerInterface::ACTION_KEY_CHANGE, AuditLoggerInterface::RESOURCE_CATEGORY, $id, $user, $request, $category->getPouch());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws TooManyRequestsException
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/categories/{id}/unlock', name: 'category_unlock', requirements: ['id' => '\d+'], methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Submit a key for a category (or one of its ancestors) — on success, returns a signed grant '
            . 'covering that whole subtree; send it back on the X-Pouch-Access-Grants header (see AccessKeyGuard)',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: AccessKeyUnlockRequestDTO::class), type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Success', content: new Model(type: AccessGrantResponseDTO::class))],
    )]
    #[OA\Response(response: 400, description: 'Category has no key set anywhere in its chain', content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 404, description: 'Category not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function unlockCategory(Request $request, int $id): Response
    {
        $this->assertGranted(CategoryVoter::UNLOCK);

        $unlockRequestDTO = $this->requestService->getRequestBodyContent($request, AccessKeyUnlockRequestDTO::class);
        $grant = $this->accessKeyService->unlockCategory(categoryId: $id, key: $unlockRequestDTO->getKey(), request: $request);

        $responseDTO = AccessGrantMapper::toResponseDTO($grant);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnprocessableContentException
     */
    #[Route('/api/items/{id}/access-key', name: 'item_access_key_set', requirements: ['id' => '\d+'], methods: [Request::METHOD_PUT])]
    #[OA\Put(
        description: "Set, change, or remove (key: null) an item's own access key",
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: AccessKeySetRequestDTO::class), type: 'object')),
        responses: [new OA\Response(response: 204, description: 'Success')],
    )]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function setItemKey(Request $request, int $id): Response
    {
        $user = $this->assertGranted(ItemVoter::MANAGE_KEY);

        $item = $this->itemService->getById($id);

        // Same rule as setCategoryKey() — an item's own key only (not its category's).
        if (false === $this->isGranted(UserRole::ADMIN->value) && false === $this->accessKeyGuard->isItemOwnKeyUnlocked($item, $request)) {
            throw new ForbiddenException(message: 'item.locked');
        }

        $setRequestDTO = $this->requestService->getRequestBodyContent($request, AccessKeySetRequestDTO::class);
        $this->accessKeyService->setItemKey(itemId: $id, key: $setRequestDTO->getKey());
        $this->auditLogger->log(AuditLoggerInterface::ACTION_KEY_CHANGE, AuditLoggerInterface::RESOURCE_ITEM, $id, $user, $request, $item->getPouch());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws TooManyRequestsException
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/{id}/unlock', name: 'item_unlock', requirements: ['id' => '\d+'], methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: "Submit a key for an item's own lock (independent of its category's) — on success, returns a "
            . 'signed grant; send it back on the X-Pouch-Access-Grants header (see AccessKeyGuard)',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: AccessKeyUnlockRequestDTO::class), type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Success', content: new Model(type: AccessGrantResponseDTO::class))],
    )]
    #[OA\Response(response: 400, description: 'Item has no key of its own', content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function unlockItem(Request $request, int $id): Response
    {
        $this->assertGranted(ItemVoter::UNLOCK);

        $unlockRequestDTO = $this->requestService->getRequestBodyContent($request, AccessKeyUnlockRequestDTO::class);
        $grant = $this->accessKeyService->unlockItem(itemId: $id, key: $unlockRequestDTO->getKey(), request: $request);

        $responseDTO = AccessGrantMapper::toResponseDTO($grant);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }
}
