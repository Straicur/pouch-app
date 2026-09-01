<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\ControllerHelper\Traits\AuthorizesRequestsTrait;
use App\DTO\Mapper\TagMapper;
use App\DTO\Request\TagCreateRequestDTO;
use App\DTO\Request\TagRenameRequestDTO;
use App\DTO\Response\TagResponseDTO;
use App\Entity\Tag;
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
use App\Security\AuthorizationServiceInterface;
use App\Security\Voter\ItemVoter;
use App\Security\Voter\TagVoter;
use App\Services\Audit\AuditLoggerInterface;
use App\Services\Request\RequestServiceInterface;
use App\Services\Tag\TagServiceInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

use function array_map;

/**
 * Every action checks auth first (401 if there's no valid access token at all),
 * then authorization (403 if logged in with an insufficient role) — see
 * TagVoter for the user/admin permission matrix on the management endpoints
 * below. GET /api/tags stays gated by ItemVoter::VIEW (ROLE_GUEST) — it's a
 * read-only, item-filtering concern, not tag management.
 */
#[OA\Response(response: 401, description: 'User not authorized', content: new Model(type: UnauthorizedExceptionModel::class))]
#[OA\Response(response: 403, description: 'Forbidden', content: new Model(type: ForbiddenExceptionModel::class))]
#[OA\Tag(name: 'Tag')]
final class TagController extends AbstractController
{
    use AuthorizesRequestsTrait;

    public function __construct(
        private readonly RequestServiceInterface $requestService,
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly TagServiceInterface $tagService,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly SerializerInterface $serializer,
    ) {}

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/tags', name: 'tag_list', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'List every tag name currently in use, alphabetically',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'string')),
            ),
        ]
    )]
    public function list(): Response
    {
        $this->assertGranted(ItemVoter::VIEW);

        $names = array_map(
            static fn (Tag $tag): string => $tag->getName(),
            $this->tagService->listInUse(),
        );

        return new Response($this->serializer->serialize(data: $names, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/tags/all', name: 'tag_list_all', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'List every tag in the current pouch, used or not — feeds the tag-management page (unlike '
            . 'GET /api/tags, which only lists names currently attached to a live item).',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: TagResponseDTO::class))),
            ),
        ]
    )]
    public function listAll(): Response
    {
        $this->assertGranted(TagVoter::VIEW);

        $tags = TagMapper::toResponseDTOList($this->tagService->listAll());

        return new Response($this->serializer->serialize(data: $tags, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws BadRequestException
     * @throws ConflictException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/tags', name: 'tag_create', methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Create a tag',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: TagCreateRequestDTO::class), type: 'object'),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new Model(type: TagResponseDTO::class)),
        ]
    )]
    #[OA\Response(response: 400, description: 'JSON data invalid', content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 409, description: 'A tag with this name already exists', content: new Model(type: ConflictExceptionModel::class))]
    public function create(Request $request): Response
    {
        $this->assertGranted(TagVoter::CREATE);

        $createRequestDTO = $this->requestService->getRequestBodyContent($request, TagCreateRequestDTO::class);

        $tag = $this->tagService->create($createRequestDTO->getName());

        $responseDTO = TagMapper::toResponseDTO($tag);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_CREATED);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws ConflictException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/tags/{id}/rename', name: 'tag_rename', requirements: ['id' => '\d+'], methods: [Request::METHOD_PATCH])]
    #[OA\Patch(
        description: 'Rename a tag',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: TagRenameRequestDTO::class), type: 'object'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new Model(type: TagResponseDTO::class)),
        ]
    )]
    #[OA\Response(response: 400, description: 'JSON data invalid', content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 404, description: 'Tag not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 409, description: 'Another tag with this name already exists', content: new Model(type: ConflictExceptionModel::class))]
    public function rename(Request $request, int $id): Response
    {
        $this->assertGranted(TagVoter::RENAME);

        $renameRequestDTO = $this->requestService->getRequestBodyContent($request, TagRenameRequestDTO::class);

        $tag = $this->tagService->rename(id: $id, name: $renameRequestDTO->getName());

        $responseDTO = TagMapper::toResponseDTO($tag);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    #[Route('/api/tags/{id}', name: 'tag_delete', requirements: ['id' => '\d+'], methods: [Request::METHOD_DELETE])]
    #[OA\Delete(
        description: 'Delete a tag (admin only) — every item tagged with it is simply untagged, nothing else is touched.',
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
        ]
    )]
    #[OA\Response(response: 404, description: 'Tag not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function delete(Request $request, int $id): Response
    {
        $user = $this->assertGranted(TagVoter::DELETE);

        $pouch = $this->tagService->getById($id)->getPouch();
        $this->tagService->delete($id);
        $this->auditLogger->log(AuditLoggerInterface::ACTION_DELETE, AuditLoggerInterface::RESOURCE_TAG, $id, $user, $request, $pouch);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
