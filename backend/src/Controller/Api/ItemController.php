<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\ControllerHelper\Traits\AuthorizesRequestsTrait;
use App\DTO\Mapper\ItemMapper;
use App\DTO\Request\ItemCreateNoteRequestDTO;
use App\DTO\Request\ItemCreateRequestDTO;
use App\DTO\Request\ItemCreateUrlRequestDTO;
use App\DTO\Request\ItemUpdateNoteRequestDTO;
use App\DTO\Request\ItemUpdateTagsRequestDTO;
use App\DTO\Response\DownloadLinkResponseDTO;
use App\DTO\Response\ItemListResponseDTO;
use App\DTO\Response\ItemResponseDTO;
use App\DTO\Response\ItemSummaryResponseDTO;
use App\DTO\Response\ItemVersionResponseDTO;
use App\DTO\Response\PublicItemLinkResponseDTO;
use App\DTO\Response\PublicItemResponseDTO;
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
use App\Security\ConfigServiceInterface;
use App\Security\SignedUrlServiceInterface;
use App\Security\Voter\ItemVoter;
use App\Services\Audit\AuditLoggerInterface;
use App\Services\Category\CategoryServiceInterface;
use App\Services\Item\ItemServiceInterface;
use App\Services\Item\ValueObject\ItemLifecycleOptions;
use App\Services\Item\ValueObject\ItemListFilter;
use App\Services\Request\RequestServiceInterface;
use App\Services\Storage\StorageServiceInterface;
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

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function fclose;
use function fopen;
use function is_resource;
use function is_scalar;
use function mb_strtolower;
use function stream_copy_to_stream;
use function trim;

/**
 * Every mutating/metadata action checks auth first (401), then ItemVoter (403)
 * — same pattern as CategoryController. After that, every action touching a
 * specific item or category (get/list/download-link/thumbnail-link/versions/
 * version-download-link/public-link/create/edit/overwrite/delete) also
 * checks AccessKeyGuard (Part 7): a locked category/item without a valid
 * grant on the request either 403s or, for list(), is just filtered out. The
 * one deliberate exception to *all* of the above is download()/thumbnail()/
 * versionDownload()/publicView(): a valid signed URL is its own, separate
 * authorization channel (product doc: "niezależny od auth-tokena
 * użytkownika"), so none of them does an auth/voter/key check — the key
 * check already happened when the signed link was generated. publicView()
 * (and the file/thumbnail links publicLink() mints alongside it) additionally
 * uses a much longer-lived signature (Part 9: "np. 24h") since it's meant to
 * be opened by someone with no account at all, not fetched immediately by
 * the app that just requested it.
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
    use AuthorizesRequestsTrait;

    private const int LINK_TTL_SECONDS = 900;

    /**
     * Part 9: "np. 24h" per the product doc — much longer than the 15-minute
     * private preview links above, since a public link is meant to be handed
     * to someone else to use whenever they get to it, not fetched immediately
     * by the app that just requested it.
     */
    private const int PUBLIC_LINK_TTL_SECONDS = 86_400;

    /**
     * GET /api/items' pagination defaults/cap — see ItemRepository::
     * findFilteredPage(). 24 keeps a default page well under a real "several
     * MB" response even with sizeable note bodies, and lines up with
     * ItemGrid's card layout; 200 is a hard ceiling so `?pageSize=999999`
     * can't be used to route around pagination entirely.
     */
    private const int DEFAULT_PAGE_SIZE = 24;

    private const int MAX_PAGE_SIZE = 200;

    public function __construct(
        private readonly RequestServiceInterface $requestService,
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly ItemServiceInterface $itemService,
        private readonly CategoryServiceInterface $categoryService,
        private readonly StorageServiceInterface $storageService,
        private readonly SignedUrlServiceInterface $signedUrlService,
        private readonly AccessKeyGuardInterface $accessKeyGuard,
        private readonly ConfigServiceInterface $configService,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly SerializerInterface $serializer,
    ) {}

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items', name: 'item_list', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'Paginated list of active (non-trashed) items, optionally filtered by category/favorite/tags, '
            . 'or full-text searched across name, tags, note content, OCR text and OpenGraph title/description. '
            . 'Each item is a summary (no $extractedText) — GET /api/items/{id} for the full item.',
        parameters: [
            new OA\Parameter(name: 'categoryIds', description: 'Comma-separated category ids, matches any', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'favorite', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'tags', description: 'Comma-separated tag names, matches any', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'q', description: 'Free-text, ranked search — meaningful from 2 characters', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', description: '1-based, defaults to 1', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pageSize', description: 'Defaults to 24, capped at 200', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new Model(type: ItemListResponseDTO::class),
            ),
        ]
    )]
    public function list(Request $request): Response
    {
        $this->assertGranted(ItemVoter::VIEW);

        $tags = $request->query->get('tags');
        $query = $request->query->get('q');

        $filter = new ItemListFilter(
            categoryIds: $this->parseCommaSeparatedIntegers($request->query->get('categoryIds')),
            favoriteOnly: $request->query->getBoolean('favorite'),
            tags: $this->parseCommaSeparatedTags($tags),
            query: null !== $query && '' !== trim($query) ? trim($query) : null,
        );

        $page = max(1, $request->query->getInt('page', 1));
        $pageSize = min(self::MAX_PAGE_SIZE, max(1, $request->query->getInt('pageSize', self::DEFAULT_PAGE_SIZE)));

        // Computed up front and passed into the query itself (see
        // ItemRepository::findFilteredPage()'s own doc comment) so a locked
        // category can never leak into COUNT/OFFSET/LIMIT. An item locked
        // only by its own key stays in the query — see the mapping loop below.
        $excludedCategoryIds = $this->accessKeyGuard->lockedCategoryIds($request);

        $result = $this->itemService->listPage(
            $filter,
            offset: ($page - 1) * $pageSize,
            limit: $pageSize,
            excludedCategoryIds: $excludedCategoryIds,
        );

        // Every returned item's *category* is already ACL-clean by
        // construction — this is a cheap defense-in-depth double-check, not
        // the primary mechanism anymore (see above). Own-key locks are
        // handled separately below, not filtered out here.
        $visibleItems = array_values(array_filter(
            $result['items'],
            fn (Item $item): bool => $this->accessKeyGuard->isCategoryUnlocked($item->getCategory(), $request),
        ));

        // Only for a page's worth of ids, and only when a free-text query is
        // actually active — see ItemService::getSearchSnippets()'s own comment.
        $snippets = null !== $filter->query
            ? $this->itemService->getSearchSnippets(array_map(static fn (Item $item): int => $item->getId(), $visibleItems), $filter->query)
            : [];

        // An item locked only by its own key no longer disappears from the
        // list entirely — it appears redacted to a name-only summary, so the
        // frontend can offer an inline unlock instead of requiring the id to
        // already be known some other way.
        $summaries = array_map(
            fn (Item $item): ItemSummaryResponseDTO => $this->accessKeyGuard->isItemOwnKeyUnlocked($item, $request)
                ? ItemMapper::toSummaryResponseDTO($item, $snippets[$item->getId()] ?? null)
                : ItemMapper::toLockedSummaryResponseDTO($item),
            $visibleItems,
        );

        $responseDTO = new ItemListResponseDTO(
            items: $summaries,
            total: $result['total'],
            page: $page,
            pageSize: $pageSize,
        );

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/{id}', name: 'item_get', requirements: ['id' => '\d+'], methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: "Get one item's metadata",
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
    public function get(Request $request, int $id): Response
    {
        $user = $this->assertGranted(ItemVoter::VIEW);

        $item = $this->itemService->getById($id);
        $this->accessKeyGuard->assertItemUnlocked($item, $request);
        $this->auditLogger->log(AuditLoggerInterface::ACTION_VIEW, AuditLoggerInterface::RESOURCE_ITEM, $id, $user, $request, $item->getPouch());

        $responseDTO = ItemMapper::toResponseDTO($item);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
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
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/{id}/note', name: 'item_update_note', requirements: ['id' => '\d+'], methods: [Request::METHOD_PATCH])]
    #[OA\Patch(
        description: "Edit a note's content",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: ItemUpdateNoteRequestDTO::class), type: 'object'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new Model(type: ItemResponseDTO::class),
            ),
        ]
    )]
    #[OA\Response(response: 400, description: "Item isn't a note, or blank/too-long content", content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function updateNote(Request $request, int $id): Response
    {
        $this->assertGranted(ItemVoter::EDIT);

        $updateRequestDTO = $this->requestService->getRequestBodyContent($request, ItemUpdateNoteRequestDTO::class);
        $this->accessKeyGuard->assertItemUnlocked($this->itemService->getById($id), $request);

        $item = $this->itemService->updateNoteContent($id, $updateRequestDTO->getContent());

        $responseDTO = ItemMapper::toResponseDTO($item);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/{id}/tags', name: 'item_update_tags', requirements: ['id' => '\d+'], methods: [Request::METHOD_PUT])]
    #[OA\Put(
        description: "Replace an item's full tag set (unknown tag names are created on the fly)",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: ItemUpdateTagsRequestDTO::class), type: 'object'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new Model(type: ItemResponseDTO::class),
            ),
        ]
    )]
    #[OA\Response(response: 400, description: 'A tag name is too long, or too many were given', content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function updateTags(Request $request, int $id): Response
    {
        $this->assertGranted(ItemVoter::EDIT);

        $updateRequestDTO = $this->requestService->getRequestBodyContent($request, ItemUpdateTagsRequestDTO::class);
        $this->accessKeyGuard->assertItemUnlocked($this->itemService->getById($id), $request);

        $item = $this->itemService->replaceTags($id, $updateRequestDTO->getTags());

        $responseDTO = ItemMapper::toResponseDTO($item);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/{id}/favorite', name: 'item_mark_favorite', requirements: ['id' => '\d+'], methods: [Request::METHOD_PUT])]
    #[OA\Put(
        description: 'Mark an item as favorite',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new Model(type: ItemResponseDTO::class),
            ),
        ]
    )]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function markFavorite(Request $request, int $id): Response
    {
        return $this->setFavoriteResponse($request, $id, true);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/{id}/favorite', name: 'item_unmark_favorite', requirements: ['id' => '\d+'], methods: [Request::METHOD_DELETE])]
    #[OA\Delete(
        description: 'Unmark an item as favorite',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new Model(type: ItemResponseDTO::class),
            ),
        ]
    )]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function unmarkFavorite(Request $request, int $id): Response
    {
        return $this->setFavoriteResponse($request, $id, false);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    private function setFavoriteResponse(Request $request, int $id, bool $favorite): Response
    {
        $this->assertGranted(ItemVoter::EDIT);

        $this->accessKeyGuard->assertItemUnlocked($this->itemService->getById($id), $request);

        $item = $this->itemService->setFavorite($id, $favorite);

        $responseDTO = ItemMapper::toResponseDTO($item);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
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
    public function delete(Request $request, int $id): Response
    {
        $user = $this->assertGranted(ItemVoter::DELETE);

        $item = $this->itemService->getById($id);
        $this->accessKeyGuard->assertItemUnlocked($item, $request);

        $this->itemService->delete($id);
        $this->auditLogger->log(AuditLoggerInterface::ACTION_DELETE, AuditLoggerInterface::RESOURCE_ITEM, $id, $user, $request, $item->getPouch());

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
    public function downloadLink(Request $request, int $id): Response
    {
        $this->assertGranted(ItemVoter::DOWNLOAD);

        $item = $this->itemService->getById($id);
        $this->accessKeyGuard->assertItemUnlocked($item, $request);

        if (null === $item->getStorageKey()) {
            throw new NotFoundException(message: 'item.no_downloadable_file');
        }

        return $this->signedLinkResponse('item_download', $this->downloadSignatureResource($id), ['id' => $id]);
    }

    /**
     * @throws ForbiddenException
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
            throw new NotFoundException(message: 'item.no_downloadable_file');
        }

        // No $user — this endpoint takes no auth token by design (see the
        // class docblock); still worth an IP, per the product doc.
        $this->auditLogger->log(AuditLoggerInterface::ACTION_DOWNLOAD, AuditLoggerInterface::RESOURCE_ITEM, $id, null, $request, $item->getPouch());

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
    public function thumbnailLink(Request $request, int $id): Response
    {
        $this->assertGranted(ItemVoter::DOWNLOAD);

        $item = $this->itemService->getById($id);
        $this->accessKeyGuard->assertItemUnlocked($item, $request);

        if (null === $item->getThumbnailStorageKey()) {
            throw new NotFoundException(message: 'item.no_thumbnail');
        }

        return $this->signedLinkResponse('item_thumbnail', $this->thumbnailSignatureResource($id), ['id' => $id]);
    }

    /**
     * @throws ForbiddenException
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
            throw new NotFoundException(message: 'item.no_thumbnail');
        }

        return $this->streamedStorageResponse(storageKey: $thumbnailKey, mimeType: 'image/jpeg', size: null, downloadFilename: null);
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
    #[Route('/api/items/{id}/file', name: 'item_overwrite_file', requirements: ['id' => '\d+'], methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: "Overwrite a FILE item's content with a new upload — same id/URL as before; the previous "
            . 'version is archived (see GET .../versions), not deleted',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(required: ['file'], properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')]),
        )),
        responses: [new OA\Response(response: 200, description: 'Success', content: new Model(type: ItemResponseDTO::class))],
    )]
    #[OA\Response(response: 400, description: 'Not a FILE item, or the new file is invalid', content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 409, description: 'A different item already has this exact content', content: new Model(type: ConflictExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function overwriteFile(Request $request, int $id): Response
    {
        $this->assertGranted(ItemVoter::EDIT);

        $this->accessKeyGuard->assertItemUnlocked($this->itemService->getById($id), $request);

        $file = $this->extractUploadedFile($request);

        $item = $this->itemService->overwriteFile(
            itemId: $id,
            tmpPath: $file->getPathname(),
            originalFilename: $file->getClientOriginalName(),
            mimeType: $file->getMimeType() ?? 'application/octet-stream',
            size: $this->fileSize($file),
        );

        $responseDTO = ItemMapper::toResponseDTO($item);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/{id}/versions', name: 'item_versions', requirements: ['id' => '\d+'], methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: "An item's version history (oldest first) — versions it held before being overwritten; its "
            . 'current content is on GET /api/items/{id} itself, not repeated here',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: ItemVersionResponseDTO::class))),
            ),
        ]
    )]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function versions(Request $request, int $id): Response
    {
        $this->assertGranted(ItemVoter::VIEW);

        $this->accessKeyGuard->assertItemUnlocked($this->itemService->getById($id), $request);

        $versions = ItemMapper::toVersionResponseDTOList($this->itemService->listVersions($id));

        return new Response($this->serializer->serialize(data: $versions, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    #[Route(
        '/api/items/{id}/versions/{version}/download-link',
        name: 'item_version_download_link',
        requirements: ['id' => '\d+', 'version' => '\d+'],
        methods: [Request::METHOD_POST],
    )]
    #[OA\Post(
        description: "Generate a short-lived (15 min) signed download URL for one of an item's archived versions "
            . '— the URL itself needs no auth token',
        responses: [new OA\Response(response: 200, description: 'Success', content: new Model(type: DownloadLinkResponseDTO::class))],
    )]
    #[OA\Response(response: 404, description: 'Item, or that version of it, not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function versionDownloadLink(Request $request, int $id, int $version): Response
    {
        $this->assertGranted(ItemVoter::DOWNLOAD);

        $item = $this->itemService->getById($id);
        $this->accessKeyGuard->assertItemUnlocked($item, $request);

        // Also confirms the version exists, before minting a link for it.
        $this->itemService->getVersion($id, $version);

        return $this->signedLinkResponse(
            'item_version_download',
            $this->versionDownloadSignatureResource($id, $version),
            ['id' => $id, 'version' => $version],
        );
    }

    /**
     * @throws ForbiddenException invalid or expired signature
     * @throws NotFoundException
     */
    #[Route(
        '/api/items/{id}/versions/{version}/download',
        name: 'item_version_download',
        requirements: ['id' => '\d+', 'version' => '\d+'],
        methods: [Request::METHOD_GET],
    )]
    #[OA\Get(
        description: 'Stream one of an item\'s archived versions — requires a valid signature from POST '
            . '.../download-link, no auth token',
        parameters: [
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'The file content, streamed')],
    )]
    #[OA\Response(response: 404, description: 'Item, or that version of it, not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function versionDownload(Request $request, int $id, int $version): StreamedResponse
    {
        $this->assertValidSignature($request, $this->versionDownloadSignatureResource($id, $version));

        // Deliberately no auth/voter check here — see the class docblock.
        $itemVersion = $this->itemService->getVersion($id, $version);
        $this->auditLogger->log(AuditLoggerInterface::ACTION_DOWNLOAD, AuditLoggerInterface::RESOURCE_ITEM, $id, null, $request, $itemVersion->getItem()->getPouch());

        return $this->streamedStorageResponse(
            storageKey: $itemVersion->getStorageKey(),
            mimeType: $itemVersion->getMimeType(),
            size: $itemVersion->getSize(),
            downloadFilename: $itemVersion->getOriginalFilename(),
        );
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/{id}/public-link', name: 'item_public_link', requirements: ['id' => '\d+'], methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Generate a public, 24h link to an item — usable by someone with no account at all. Includes a '
            . 'view link (item metadata) and, if the item has them, a file/thumbnail download link — all three share '
            . 'the same expiry.',
        responses: [new OA\Response(response: 200, description: 'Success', content: new Model(type: PublicItemLinkResponseDTO::class))],
    )]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function publicLink(Request $request, int $id): Response
    {
        $this->assertGranted(ItemVoter::DOWNLOAD);

        $item = $this->itemService->getById($id);
        $this->accessKeyGuard->assertItemUnlocked($item, $request);

        $view = $this->publicSignedUrl('item_public_view', $this->publicViewSignatureResource($id), ['id' => $id]);

        $downloadUrl = null !== $item->getStorageKey()
            ? $this->publicSignedUrl('item_download', $this->downloadSignatureResource($id), ['id' => $id])['url']
            : null;

        $thumbnailUrl = null !== $item->getThumbnailStorageKey()
            ? $this->publicSignedUrl('item_thumbnail', $this->thumbnailSignatureResource($id), ['id' => $id])['url']
            : null;

        $responseDTO = new PublicItemLinkResponseDTO(
            viewUrl: $view['url'],
            downloadUrl: $downloadUrl,
            thumbnailUrl: $thumbnailUrl,
            expiresAt: $view['expiresAt'],
        );

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/public/items/{id}', name: 'item_public_view', requirements: ['id' => '\d+'], methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: "An item's public, read-only details — requires a valid signature from POST "
            . '.../public-link, no auth token',
        parameters: [
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Success', content: new Model(type: PublicItemResponseDTO::class))],
    )]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function publicView(Request $request, int $id): Response
    {
        $this->assertValidSignature($request, $this->publicViewSignatureResource($id));

        $item = $this->itemService->getById($id);
        $this->auditLogger->log(AuditLoggerInterface::ACTION_VIEW, AuditLoggerInterface::RESOURCE_ITEM, $id, null, $request, $item->getPouch());

        $responseDTO = ItemMapper::toPublicResponseDTO($item);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
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
     * @throws BadRequestException
     */
    private function extractUploadedFile(Request $request): UploadedFile
    {
        $file = $request->files->get('file');
        if (false === $file instanceof UploadedFile || false === $file->isValid()) {
            throw new BadRequestException(message: 'item.file_upload_missing');
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
            throw new BadRequestException(message: 'item.file_size_unknown');
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
     * @param array<string, int|string> $routeParams route params besides expires/signature (e.g. ['id' => $id], or
     *                                               ['id' => $id, 'version' => $version] for a version download link)
     */
    private function signedLinkResponse(string $routeName, string $signatureResource, array $routeParams): Response
    {
        $signed = $this->signedUrlService->sign($signatureResource, self::LINK_TTL_SECONDS);

        // Relative, not ABSOLUTE_URL: the frontend fetches this from whatever
        // origin is currently serving it (the dev Vite proxy or, in prod, the
        // same domain as the API) — an absolute URL would instead embed
        // *this request's* Host header, which behind the dev proxy is the
        // internal Docker service name and unreachable from the browser.
        // Part 9's genuinely-external public links use publicSignedUrl()
        // below instead, precisely because they need a real absolute URL.
        $url = $this->generateUrl(
            $routeName,
            [...$routeParams, 'expires' => $signed['expires'], 'signature' => $signed['signature']],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        $responseDTO = new DownloadLinkResponseDTO(
            url: $url,
            expiresAt: new DateTimeImmutable('@' . $signed['expires'])->format(DateTimeInterface::ATOM),
        );

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * Part 9's counterpart to signedLinkResponse() above — same signing, but
     * a genuinely absolute URL (ConfigService::getPublicBaseUrl(), not this
     * request's Host) since the recipient has no app context to resolve a
     * relative one against, and returns the pieces rather than a Response
     * since publicLink() bundles up to three of these into one DTO.
     *
     * @param array<string, int|string> $routeParams
     *
     * @return array{url: string, expiresAt: string}
     */
    private function publicSignedUrl(string $routeName, string $signatureResource, array $routeParams): array
    {
        $signed = $this->signedUrlService->sign($signatureResource, self::PUBLIC_LINK_TTL_SECONDS);

        $path = $this->generateUrl(
            $routeName,
            [...$routeParams, 'expires' => $signed['expires'], 'signature' => $signed['signature']],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        return [
            'url'       => $this->configService->getPublicBaseUrl() . $path,
            'expiresAt' => new DateTimeImmutable('@' . $signed['expires'])->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @throws ForbiddenException
     */
    private function assertValidSignature(Request $request, string $signatureResource): void
    {
        $expires = $request->query->getInt('expires');
        $signature = $request->query->getString('signature');

        if (false === $this->signedUrlService->isValid($signatureResource, $expires, $signature)) {
            throw new ForbiddenException(message: 'item.link_invalid');
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

    private function versionDownloadSignatureResource(int $id, int $version): string
    {
        return 'item-version-download:' . $id . ':' . $version;
    }

    private function publicViewSignatureResource(int $id): string
    {
        return 'item-public-view:' . $id;
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Shared by list()'s `tags` filter and parseItemCreateRequestDTO()'s
     * `tags` field — same comma-separated-string-to-normalized-list parsing
     * either way.
     *
     * @return list<string>
     */
    private function parseCommaSeparatedTags(mixed $raw): array
    {
        $value = $this->nullableString($raw);

        if (null === $value || '' === $value) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $tag): string => mb_strtolower(trim($tag)), explode(',', $value)),
            static fn (string $tag): bool => '' !== $tag,
        ));
    }

    /**
     * @return list<int>
     */
    private function parseCommaSeparatedIntegers(mixed $raw): array
    {
        $value = $this->nullableString($raw);

        if (null === $value || '' === $value) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $id): int => (int) trim($id), explode(',', $value)),
            static fn (int $id): bool => 0 < $id,
        ));
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
