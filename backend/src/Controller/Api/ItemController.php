<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\DTO\Mapper\ItemMapper;
use App\DTO\Request\ItemCreateRequestDTO;
use App\DTO\Request\ItemCreateUrlRequestDTO;
use App\DTO\Response\DownloadLinkResponseDTO;
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
use App\Item\ItemLifecycleOptions;
use App\Item\ItemServiceInterface;
use App\Security\AuthServiceInterface;
use App\Security\SignedUrlServiceInterface;
use App\Security\Voter\ItemVoter;
use App\Service\RequestServiceInterface;
use App\Storage\StorageServiceInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

use function fclose;
use function fopen;
use function is_resource;
use function is_scalar;
use function stream_copy_to_stream;

/**
 * Every mutating/metadata action checks auth first (401), then ItemVoter (403)
 * — same pattern as CategoryController. The one deliberate exception is the
 * download()/thumbnail() streams: a valid signed URL is its own, separate
 * authorization channel (product doc: "niezależny od auth-tokena
 * użytkownika"), so neither does an auth/voter check.
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
#[OA\Tag(name: 'Item')]
final class ItemController extends AbstractController
{
    private const int LINK_TTL_SECONDS = 900;

    public function __construct(
        private readonly RequestServiceInterface $requestService,
        private readonly AuthServiceInterface $authService,
        private readonly ItemServiceInterface $itemService,
        private readonly StorageServiceInterface $storageService,
        private readonly SignedUrlServiceInterface $signedUrlService,
        private readonly SerializerInterface $serializer,
    ) {}

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items', name: 'item_list', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'List active (non-trashed) items, optionally filtered by category',
        parameters: [
            new OA\Parameter(name: 'categoryId', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: ItemResponseDTO::class)),
                ),
            ),
        ]
    )]
    public function list(Request $request): Response
    {
        $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(ItemVoter::VIEW)) {
            throw new ForbiddenException();
        }

        $categoryId = $request->query->get('categoryId');
        $items = ItemMapper::toResponseDTOList($this->itemService->list(null !== $categoryId ? (int) $categoryId : null));

        return new Response($this->serializer->serialize(data: $items, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/{id}', name: 'item_get', requirements: ['id' => '\d+'], methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'Get one item\'s metadata',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new Model(type: ItemResponseDTO::class),
            ),
        ]
    )]
    #[OA\Response(
        response: 404,
        description: 'Item not found',
        content: new Model(type: NotFoundExceptionModel::class)
    )]
    public function get(int $id): Response
    {
        $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(ItemVoter::VIEW)) {
            throw new ForbiddenException();
        }

        $responseDTO = ItemMapper::toResponseDTO($this->itemService->getById($id));

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException             if categoryId doesn't exist
     * @throws BadRequestException           invalid file (extension/size) or TTL input
     * @throws ConflictException             identical content already exists
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
                    new OA\Property(property: 'keepForever', type: 'boolean'),
                    new OA\Property(property: 'ttlPreset', type: 'string', nullable: true, enum: ['1h', '7d', '30d']),
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
        $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(ItemVoter::CREATE)) {
            throw new ForbiddenException();
        }

        $file = $this->extractUploadedFile($request);
        $createRequestDTO = $this->parseItemCreateRequestDTO($request);

        $item = $this->itemService->createFile(
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
     * @throws NotFoundException             if categoryId doesn't exist
     * @throws BadRequestException           invalid image (extension/size) or TTL input
     * @throws ConflictException             identical content already exists
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
                    new OA\Property(property: 'ttlPreset', type: 'string', nullable: true, enum: ['1h', '7d', '30d']),
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
        $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(ItemVoter::CREATE)) {
            throw new ForbiddenException();
        }

        $file = $this->extractUploadedFile($request);
        $createRequestDTO = $this->parseItemCreateRequestDTO($request);

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
     * @throws NotFoundException             if categoryId doesn't exist
     * @throws BadRequestException           malformed URL or TTL input
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
        $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(ItemVoter::CREATE)) {
            throw new ForbiddenException();
        }

        $createRequestDTO = $this->requestService->getRequestBodyContent($request, ItemCreateUrlRequestDTO::class);

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
     */
    #[Route('/api/items/{id}', name: 'item_delete', requirements: ['id' => '\d+'], methods: [Request::METHOD_DELETE])]
    #[OA\Delete(
        description: 'Move an item to the trash (permanently deleted after 7 days by app:item:gc)',
        responses: [
            new OA\Response(response: 204, description: 'Trashed'),
        ]
    )]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function delete(int $id): Response
    {
        $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(ItemVoter::DELETE)) {
            throw new ForbiddenException();
        }

        $this->itemService->delete($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/{id}/download-link', name: 'item_download_link', requirements: ['id' => '\d+'], methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Generate a short-lived (15 min) signed download URL for the item\'s primary file — the URL itself needs no auth token',
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new Model(type: DownloadLinkResponseDTO::class)),
        ]
    )]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function downloadLink(int $id): Response
    {
        $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(ItemVoter::DOWNLOAD)) {
            throw new ForbiddenException();
        }

        $item = $this->itemService->getById($id);
        if (null === $item->getStorageKey()) {
            throw new NotFoundException(message: 'This item has no downloadable file');
        }

        return $this->signedLinkResponse('item_download', $this->downloadSignatureResource($id), $id);
    }

    /**
     * @throws ForbiddenException invalid or expired signature
     * @throws NotFoundException
     */
    #[Route('/api/items/{id}/download', name: 'item_download', requirements: ['id' => '\d+'], methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'Stream an item\'s primary file — requires a valid signature from POST .../download-link, no auth token',
        parameters: [
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The file content, streamed'),
        ]
    )]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function download(Request $request, int $id): StreamedResponse
    {
        $this->assertValidSignature($request, $this->downloadSignatureResource($id));

        // Deliberately no auth/voter check here — see the class docblock.
        $item = $this->itemService->getById($id);
        $storageKey = $item->getStorageKey();
        if (null === $storageKey) {
            throw new NotFoundException(message: 'This item has no downloadable file');
        }

        return $this->streamedStorageResponse(
            storageKey: $storageKey,
            mimeType: $item->getMimeType() ?? 'application/octet-stream',
            size: $item->getSize(),
            downloadFilename: $item->getOriginalFilename(),
        );
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/{id}/thumbnail-link', name: 'item_thumbnail_link', requirements: ['id' => '\d+'], methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Generate a short-lived (15 min) signed URL for the item\'s thumbnail (photo/URL items only) — no auth token needed',
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new Model(type: DownloadLinkResponseDTO::class)),
        ]
    )]
    #[OA\Response(response: 404, description: 'Item or thumbnail not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function thumbnailLink(int $id): Response
    {
        $this->authService->getUserFromAccessToken();

        if (false === $this->isGranted(ItemVoter::DOWNLOAD)) {
            throw new ForbiddenException();
        }

        $item = $this->itemService->getById($id);
        if (null === $item->getThumbnailStorageKey()) {
            throw new NotFoundException(message: 'This item has no thumbnail');
        }

        return $this->signedLinkResponse('item_thumbnail', $this->thumbnailSignatureResource($id), $id);
    }

    /**
     * @throws ForbiddenException invalid or expired signature
     * @throws NotFoundException
     */
    #[Route('/api/items/{id}/thumbnail', name: 'item_thumbnail', requirements: ['id' => '\d+'], methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'Stream an item\'s thumbnail — requires a valid signature from POST .../thumbnail-link, no auth token',
        parameters: [
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The thumbnail (JPEG), streamed'),
        ]
    )]
    #[OA\Response(response: 404, description: 'Item or thumbnail not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function thumbnail(Request $request, int $id): StreamedResponse
    {
        $this->assertValidSignature($request, $this->thumbnailSignatureResource($id));

        // Deliberately no auth/voter check here — see the class docblock.
        $item = $this->itemService->getById($id);
        $thumbnailKey = $item->getThumbnailStorageKey();
        if (null === $thumbnailKey) {
            throw new NotFoundException(message: 'This item has no thumbnail');
        }

        return $this->streamedStorageResponse(storageKey: $thumbnailKey, mimeType: 'image/jpeg', size: null, downloadFilename: null);
    }

    private function createdResponse(Item $item): Response
    {
        $responseDTO = ItemMapper::toResponseDTO($item);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_CREATED);
    }

    /**
     * @throws BadRequestException
     */
    private function extractUploadedFile(Request $request): UploadedFile
    {
        $file = $request->files->get('file');
        if (false === $file instanceof UploadedFile || false === $file->isValid()) {
            throw new BadRequestException(message: 'Missing or invalid "file" upload');
        }

        return $file;
    }

    /**
     * @throws BadRequestException
     */
    private function fileSize(UploadedFile $file): int
    {
        $size = $file->getSize();
        if (false === $size) {
            throw new BadRequestException(message: 'Could not determine the uploaded file\'s size');
        }

        return $size;
    }

    /**
     * @throws UnprocessableContentException
     */
    private function parseItemCreateRequestDTO(Request $request): ItemCreateRequestDTO
    {
        $dto = new ItemCreateRequestDTO(
            categoryId: $request->request->getInt('categoryId'),
            name: $this->nullableString($request->request->get('name')),
            keepForever: $request->request->getBoolean('keepForever'),
            ttlPreset: $this->nullableString($request->request->get('ttlPreset')),
            expiresAt: $this->nullableString($request->request->get('expiresAt')),
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

    private function signedLinkResponse(string $routeName, string $signatureResource, int $id): Response
    {
        $signed = $this->signedUrlService->sign($signatureResource, self::LINK_TTL_SECONDS);

        // Relative, not ABSOLUTE_URL: the frontend fetches this from whatever
        // origin is currently serving it (the dev Vite proxy or, in prod, the
        // same domain as the API) — an absolute URL would instead embed
        // *this request's* Host header, which behind the dev proxy is the
        // internal Docker service name and unreachable from the browser.
        // Part 9's genuinely-external public links need their own solution
        // (a configured public base URL), not this one.
        $url = $this->generateUrl(
            $routeName,
            ['id' => $id, 'expires' => $signed['expires'], 'signature' => $signed['signature']],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        $responseDTO = new DownloadLinkResponseDTO(
            url: $url,
            expiresAt: new DateTimeImmutable('@' . $signed['expires'])->format(DateTimeInterface::ATOM),
        );

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws ForbiddenException
     */
    private function assertValidSignature(Request $request, string $signatureResource): void
    {
        $expires = $request->query->getInt('expires');
        $signature = $request->query->getString('signature');

        if (false === $this->signedUrlService->isValid($signatureResource, $expires, $signature)) {
            throw new ForbiddenException(message: 'Invalid or expired link');
        }
    }

    private function streamedStorageResponse(string $storageKey, string $mimeType, ?int $size, ?string $downloadFilename): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($storageKey): void {
            $sourceStream = $this->storageService->download($storageKey);
            $outputStream = fopen('php://output', 'wb');
            if (false === is_resource($outputStream)) {
                throw new RuntimeException('Could not open php://output for writing');
            }

            stream_copy_to_stream($sourceStream, $outputStream);
            fclose($sourceStream);
            fclose($outputStream);
        });

        $response->headers->set('Content-Type', $mimeType);
        if (null !== $size) {
            $response->headers->set('Content-Length', (string) $size);
        }
        if (null !== $downloadFilename) {
            $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $downloadFilename));
        }

        return $response;
    }

    private function downloadSignatureResource(int $id): string
    {
        return 'item-download:' . $id;
    }

    private function thumbnailSignatureResource(int $id): string
    {
        return 'item-thumbnail:' . $id;
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
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
            throw new BadRequestException(message: 'expiresAt is not a valid date', previous: $exception);
        }
    }
}
