<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\Audit\AuditLoggerInterface;
use App\Category\CategoryExportServiceInterface;
use App\Category\CategoryServiceInterface;
use App\DTO\Mapper\CategoryMapper;
use App\DTO\Request\CategoryCreateRequestDTO;
use App\DTO\Request\CategoryMoveRequestDTO;
use App\DTO\Request\CategoryRenameRequestDTO;
use App\DTO\Response\CategoryResponseDTO;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException\UnprocessableContentException;
use App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException\UnprocessableContentExceptionModel;
use App\Security\AuthServiceInterface;
use App\Security\Voter\CategoryVoter;
use App\Service\RequestServiceInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

use function fclose;
use function fopen;
use function is_resource;
use function stream_copy_to_stream;
use function unlink;

/**
 * Every action checks auth first (401 if there's no valid access token at all),
 * then authorization via CategoryVoter (403 if logged in with an insufficient
 * role) — see CategoryVoter for the guest/user/admin permission matrix.
 */
#[OA\Response(
    response: 401,
    description: 'User not authorized',
    content: new Model(type: UnauthorizedExceptionModel::class)
)]
#[OA\Response(
    response: 403,
    description: 'Forbidden — logged in, but role doesn\'t allow this action',
    content: new Model(type: ForbiddenExceptionModel::class)
)]
#[OA\Tag(name: 'Category')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly RequestServiceInterface $requestService,
        private readonly AuthServiceInterface $authService,
        private readonly CategoryServiceInterface $categoryService,
        private readonly CategoryExportServiceInterface $categoryExportService,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly SerializerInterface $serializer,
    ) {}

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/categories', name: 'category_list', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'List all categories (flat — use parentId to reconstruct the tree)',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: CategoryResponseDTO::class)),
                ),
            ),
        ]
    )]
    public function list(): Response
    {
        $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(CategoryVoter::VIEW)) {
            throw new ForbiddenException();
        }

        $categories = CategoryMapper::toResponseDTOList($this->categoryService->list());

        return new Response($this->serializer->serialize(data: $categories, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException             if parentId is given but doesn't exist
     * @throws BadRequestException
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/categories', name: 'category_create', methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Create a category, optionally under an existing parent',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: new Model(type: CategoryCreateRequestDTO::class),
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created',
                content: new Model(type: CategoryResponseDTO::class),
            ),
        ]
    )]
    #[OA\Response(
        response: 400,
        description: 'JSON Data Invalid',
        content: new Model(type: BadRequestExceptionModel::class)
    )]
    #[OA\Response(
        response: 404,
        description: 'Parent category not found',
        content: new Model(type: NotFoundExceptionModel::class)
    )]
    #[OA\Response(
        response: 422,
        description: 'Unprocessable Content',
        content: new Model(type: UnprocessableContentExceptionModel::class)
    )]
    public function create(Request $request): Response
    {
        $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(CategoryVoter::CREATE)) {
            throw new ForbiddenException();
        }

        $createRequestDTO = $this->requestService->getRequestBodyContent($request, CategoryCreateRequestDTO::class);

        $category = $this->categoryService->create(
            name: $createRequestDTO->getName(),
            parentId: $createRequestDTO->getParentId(),
        );

        $responseDTO = CategoryMapper::toResponseDTO($category);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_CREATED);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/categories/{id}/rename', name: 'category_rename', requirements: ['id' => '\d+'], methods: [Request::METHOD_PATCH])]
    #[OA\Patch(
        description: 'Rename a category',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: new Model(type: CategoryRenameRequestDTO::class),
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new Model(type: CategoryResponseDTO::class),
            ),
        ]
    )]
    #[OA\Response(
        response: 400,
        description: 'JSON Data Invalid',
        content: new Model(type: BadRequestExceptionModel::class)
    )]
    #[OA\Response(
        response: 404,
        description: 'Category not found',
        content: new Model(type: NotFoundExceptionModel::class)
    )]
    #[OA\Response(
        response: 422,
        description: 'Unprocessable Content',
        content: new Model(type: UnprocessableContentExceptionModel::class)
    )]
    public function rename(Request $request, int $id): Response
    {
        $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(CategoryVoter::RENAME)) {
            throw new ForbiddenException();
        }

        $renameRequestDTO = $this->requestService->getRequestBodyContent($request, CategoryRenameRequestDTO::class);

        $category = $this->categoryService->rename(id: $id, name: $renameRequestDTO->getName());

        $responseDTO = CategoryMapper::toResponseDTO($category);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/categories/{id}/move', name: 'category_move', requirements: ['id' => '\d+'], methods: [Request::METHOD_PATCH])]
    #[OA\Patch(
        description: 'Move a category under a new parent (or to the root, if parentId is null)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: new Model(type: CategoryMoveRequestDTO::class),
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new Model(type: CategoryResponseDTO::class),
            ),
        ]
    )]
    #[OA\Response(
        response: 400,
        description: 'Would move the category into itself or one of its own descendants',
        content: new Model(type: BadRequestExceptionModel::class)
    )]
    #[OA\Response(
        response: 404,
        description: 'Category or target parent not found',
        content: new Model(type: NotFoundExceptionModel::class)
    )]
    #[OA\Response(
        response: 422,
        description: 'Unprocessable Content',
        content: new Model(type: UnprocessableContentExceptionModel::class)
    )]
    public function move(Request $request, int $id): Response
    {
        $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(CategoryVoter::MOVE)) {
            throw new ForbiddenException();
        }

        $moveRequestDTO = $this->requestService->getRequestBodyContent($request, CategoryMoveRequestDTO::class);

        $category = $this->categoryService->move(id: $id, parentId: $moveRequestDTO->getParentId());

        $responseDTO = CategoryMapper::toResponseDTO($category);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    #[Route('/api/categories/{id}', name: 'category_delete', requirements: ['id' => '\d+'], methods: [Request::METHOD_DELETE])]
    #[OA\Delete(
        description: 'Delete a category (admin only) — cascades to its descendants',
        responses: [
            new OA\Response(
                response: 204,
                description: 'Deleted',
            ),
        ]
    )]
    #[OA\Response(
        response: 404,
        description: 'Category not found',
        content: new Model(type: NotFoundExceptionModel::class)
    )]
    public function delete(Request $request, int $id): Response
    {
        $user = $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(CategoryVoter::DELETE)) {
            throw new ForbiddenException();
        }

        $this->categoryService->delete($id);
        $this->auditLogger->log(AuditLoggerInterface::ACTION_DELETE, AuditLoggerInterface::RESOURCE_CATEGORY, $id, $user, $request);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Part 9. Streams a ZIP of the whole (sub)tree — see
     * CategoryExportServiceInterface::buildZip() for what goes in and how
     * Part 7 locks are handled (filtered out, not a hard failure).
     *
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    #[Route('/api/categories/{id}/export', name: 'category_export', requirements: ['id' => '\d+'], methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'Download a category (and its full subtree) as a ZIP, preserving folder structure',
        responses: [
            new OA\Response(response: 200, description: 'The archive, streamed'),
        ]
    )]
    #[OA\Response(
        response: 404,
        description: 'Category not found',
        content: new Model(type: NotFoundExceptionModel::class)
    )]
    public function export(Request $request, int $id): StreamedResponse
    {
        $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(CategoryVoter::VIEW)) {
            throw new ForbiddenException();
        }

        $category = $this->categoryService->getById($id);
        $zipPath = $this->categoryExportService->buildZip($id, $request);

        $response = new StreamedResponse(function () use ($zipPath): void {
            $sourceStream = fopen($zipPath, 'rb');
            if (false === is_resource($sourceStream)) {
                throw new RuntimeException('Could not open the temporary export archive for reading');
            }

            $outputStream = fopen('php://output', 'wb');
            if (false === is_resource($outputStream)) {
                fclose($sourceStream);

                throw new RuntimeException('Could not open php://output for writing');
            }

            stream_copy_to_stream($sourceStream, $outputStream);
            fclose($sourceStream);
            fclose($outputStream);
            unlink($zipPath);
        });

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $category->getName() . '.zip'),
        );

        return $response;
    }
}
