<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\ControllerHelper\Traits\AuthorizesRequestsTrait;
use App\ControllerHelper\Traits\ExtractsUploadedFileTrait;
use App\ControllerHelper\Traits\ParsesCommaSeparatedValuesTrait;
use App\DTO\Mapper\ItemMapper;
use App\DTO\Request\ItemCreateNoteRequestDTO;
use App\DTO\Request\ItemCreateRequestDTO;
use App\DTO\Request\ItemCreateUrlRequestDTO;
use App\DTO\Response\ItemResponseDTO;
use App\Entity\Item;
use App\Enum\TtlPreset;
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
use App\Security\AccessKey\AccessKeyGuardInterface;
use App\Security\AuthorizationServiceInterface;
use App\Security\Voter\ItemVoter;
use App\Services\Category\CategoryServiceInterface;
use App\Services\Item\ItemServiceInterface;
use App\Services\Item\ValueObject\ItemLifecycleOptions;
use App\Services\Request\RequestServiceInterface;
use DateTimeImmutable;
use Exception;
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
 * Item creation — one action per type (file/photo/url/note), see
 * ItemController's own class docblock for how this file relates to the rest
 * of the item resource's controllers. Every action here checks the target
 * category is accessible (exists, and unlocked for this request) *before*
 * calling into ItemServiceInterface, so a locked/foreign category 403s/404s
 * the same way regardless of which create action hit it.
 */
#[OA\Response(response: 401, description: 'User not authorized', content: new Model(type: UnauthorizedExceptionModel::class))]
#[OA\Response(response: 403, description: 'Forbidden — logged in, but role doesn\'t allow this action', content: new Model(type: ForbiddenExceptionModel::class))]
#[OA\Tag(name: 'Item')]
final class ItemCreateController extends AbstractController
{
    use AuthorizesRequestsTrait;
    use ExtractsUploadedFileTrait;
    use ParsesCommaSeparatedValuesTrait;

    public function __construct(
        private readonly RequestServiceInterface $requestService,
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly ItemServiceInterface $itemService,
        private readonly CategoryServiceInterface $categoryService,
        private readonly AccessKeyGuardInterface $accessKeyGuard,
        private readonly SerializerInterface $serializer,
    ) {}

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws ConflictException
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/files', name: 'item_create_file', methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Upload a general file into a category (streamed straight to storage — never buffered whole in PHP memory)',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['file', 'categoryId'],
                properties: [
                    new OA\Property(property: 'file', type: 'string', format: 'binary'),
                    new OA\Property(property: 'categoryId', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string', nullable: true),
                    new OA\Property(property: 'content', description: 'Optional free-text description', type: 'string', nullable: true),
                    new OA\Property(property: 'keepForever', type: 'boolean'),
                    new OA\Property(property: 'ttlPreset', type: 'string', enum: ['1h', '1d', '7d', '30d'], nullable: true),
                    new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', nullable: true),
                ],
            ),
        )),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created',
                content: new Model(type: ItemResponseDTO::class),
            ),
        ]
    )]
    #[OA\Response(response: 400, description: 'Invalid file (extension/size) or TTL input', content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 404, description: 'Category not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 409, description: 'Identical content already exists', content: new Model(type: ConflictExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function createFile(Request $request): Response
    {
        $this->assertGranted(ItemVoter::CREATE);

        $file = $this->extractUploadedFile($request);
        $createRequestDTO = $this->parseItemCreateRequestDTO($request);
        $this->assertCategoryAccessible($createRequestDTO->getCategoryId(), $request);

        $item = $this->itemService->createFile(
            categoryId: $createRequestDTO->getCategoryId(),
            tmpPath: $file->getPathname(),
            originalFilename: $file->getClientOriginalName(),
            mimeType: $file->getMimeType() ?? 'application/octet-stream',
            size: $this->fileSize($file),
            options: $this->toLifecycleOptions($createRequestDTO),
            content: $createRequestDTO->getContent(),
            tags: $createRequestDTO->getTags(),
        );

        return $this->createdResponse($item);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws ConflictException
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/photos', name: 'item_create_photo', methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Upload a photo into a category — thumbnail + OCR happen asynchronously (processingStatus starts "pending")',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['file', 'categoryId'],
                properties: [
                    new OA\Property(property: 'file', type: 'string', format: 'binary'),
                    new OA\Property(property: 'categoryId', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string', nullable: true),
                    new OA\Property(property: 'keepForever', type: 'boolean'),
                    new OA\Property(property: 'ttlPreset', type: 'string', enum: ['1h', '1d', '7d', '30d'], nullable: true),
                    new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', nullable: true),
                ],
            ),
        )),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created (processingStatus: pending)',
                content: new Model(type: ItemResponseDTO::class),
            ),
        ]
    )]
    #[OA\Response(response: 400, description: 'Invalid image (extension/size) or TTL input', content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 404, description: 'Category not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 409, description: 'Identical content already exists', content: new Model(type: ConflictExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function createPhoto(Request $request): Response
    {
        $this->assertGranted(ItemVoter::CREATE);

        $file = $this->extractUploadedFile($request);
        $createRequestDTO = $this->parseItemCreateRequestDTO($request);
        $this->assertCategoryAccessible($createRequestDTO->getCategoryId(), $request);

        $item = $this->itemService->createPhoto(
            categoryId: $createRequestDTO->getCategoryId(),
            tmpPath: $file->getPathname(),
            originalFilename: $file->getClientOriginalName(),
            mimeType: $file->getMimeType() ?? 'application/octet-stream',
            size: $this->fileSize($file),
            options: $this->toLifecycleOptions($createRequestDTO),
        );

        return $this->createdResponse($item);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/urls', name: 'item_create_url', methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Save a URL into a category — OpenGraph scraping happens asynchronously (processingStatus starts "pending")',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: ItemCreateUrlRequestDTO::class), type: 'object'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created (processingStatus: pending)',
                content: new Model(type: ItemResponseDTO::class),
            ),
        ]
    )]
    #[OA\Response(response: 400, description: 'Malformed URL or TTL input', content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 404, description: 'Category not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function createUrl(Request $request): Response
    {
        $this->assertGranted(ItemVoter::CREATE);

        $createRequestDTO = $this->requestService->getRequestBodyContent($request, ItemCreateUrlRequestDTO::class);
        $this->assertCategoryAccessible($createRequestDTO->getCategoryId(), $request);

        $item = $this->itemService->createUrl(
            categoryId: $createRequestDTO->getCategoryId(),
            url: $createRequestDTO->getUrl(),
            options: new ItemLifecycleOptions(
                name: $createRequestDTO->getName(),
                keepForever: $createRequestDTO->isKeepForever(),
                ttlPreset: null !== $createRequestDTO->getTtlPreset() ? TtlPreset::from($createRequestDTO->getTtlPreset()) : null,
                customExpiresAt: $this->parseCustomExpiresAt($createRequestDTO->getExpiresAt()),
            ),
        );

        return $this->createdResponse($item);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/notes', name: 'item_create_note', methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Create a note (markdown text) in a category',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: ItemCreateNoteRequestDTO::class), type: 'object'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created',
                content: new Model(type: ItemResponseDTO::class),
            ),
        ]
    )]
    #[OA\Response(response: 400, description: 'Blank/too-long content or TTL input', content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 404, description: 'Category not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function createNote(Request $request): Response
    {
        $this->assertGranted(ItemVoter::CREATE);

        $createRequestDTO = $this->requestService->getRequestBodyContent($request, ItemCreateNoteRequestDTO::class);
        $this->assertCategoryAccessible($createRequestDTO->getCategoryId(), $request);

        $item = $this->itemService->createNote(
            categoryId: $createRequestDTO->getCategoryId(),
            content: $createRequestDTO->getContent(),
            options: new ItemLifecycleOptions(
                name: $createRequestDTO->getName(),
                keepForever: $createRequestDTO->isKeepForever(),
                ttlPreset: null !== $createRequestDTO->getTtlPreset() ? TtlPreset::from($createRequestDTO->getTtlPreset()) : null,
                customExpiresAt: $this->parseCustomExpiresAt($createRequestDTO->getExpiresAt()),
            ),
            tags: $createRequestDTO->getTags(),
        );

        return $this->createdResponse($item);
    }

    /**
     * @throws NotFoundException  if $categoryId doesn't exist
     * @throws ForbiddenException if the category (or an ancestor) is locked and $request carries no valid grant
     */
    private function assertCategoryAccessible(int $categoryId, Request $request): void
    {
        $category = $this->categoryService->getById($categoryId);
        $this->accessKeyGuard->assertCategoryUnlocked($category, $request);
    }

    private function createdResponse(Item $item): Response
    {
        $responseDTO = ItemMapper::toResponseDTO($item);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_CREATED);
    }

    /**
     * @throws UnprocessableContentException
     */
    private function parseItemCreateRequestDTO(Request $request): ItemCreateRequestDTO
    {
        $dto = new ItemCreateRequestDTO(
            categoryId: $request->request->getInt('categoryId'),
            name: $this->nullableString($request->request->get('name')),
            content: $this->nullableString($request->request->get('content')),
            keepForever: $request->request->getBoolean('keepForever'),
            ttlPreset: $this->nullableString($request->request->get('ttlPreset')),
            expiresAt: $this->nullableString($request->request->get('expiresAt')),
            tags: $this->parseCommaSeparatedTags($request->request->get('tags')),
        );
        $this->requestService->validate($dto);

        return $dto;
    }

    /**
     * @throws BadRequestException
     */
    private function toLifecycleOptions(ItemCreateRequestDTO $dto): ItemLifecycleOptions
    {
        return new ItemLifecycleOptions(
            name: $dto->getName(),
            keepForever: $dto->isKeepForever(),
            ttlPreset: null !== $dto->getTtlPreset() ? TtlPreset::from($dto->getTtlPreset()) : null,
            customExpiresAt: $this->parseCustomExpiresAt($dto->getExpiresAt()),
        );
    }

    /**
     * @throws BadRequestException
     */
    private function parseCustomExpiresAt(?string $expiresAt): ?DateTimeImmutable
    {
        if (null === $expiresAt || '' === $expiresAt) {
            return null;
        }

        try {
            return new DateTimeImmutable($expiresAt);
        } catch (Exception $exception) {
            throw new BadRequestException(message: 'item.expires_at_invalid', previous: $exception);
        }
    }
}
