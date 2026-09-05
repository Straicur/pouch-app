<?php

declare(strict_types = 1);

namespace App\Controller\Api\Admin;

use App\ControllerHelper\Enum\ItemListPageSize;
use App\ControllerHelper\Factory\StreamedFileResponseFactoryInterface;
use App\ControllerHelper\Traits\AuthorizesRequestsTrait;
use App\ControllerHelper\Traits\ParsesQueryParamsTrait;
use App\DTO\Mapper\AdminMapper;
use App\DTO\Mapper\ItemMapper;
use App\DTO\Request\AdminExtendExpiryRequestDTO;
use App\DTO\Request\StorageLimitSetRequestDTO;
use App\DTO\Request\TechnicalBreakEnableRequestDTO;
use App\DTO\Response\AuditLogResponseDTO;
use App\DTO\Response\GcRunLogResponseDTO;
use App\DTO\Response\ItemListResponseDTO;
use App\DTO\Response\ItemResponseDTO;
use App\DTO\Response\StorageReportResponseDTO;
use App\DTO\Response\TechnicalBreakResponseDTO;
use App\Entity\GcRunLog;
use App\Enum\ItemType;
use App\Enum\TtlPreset;
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
use App\Repository\AuditLogRepository;
use App\Repository\GcRunLogRepository;
use App\Repository\ItemRepository;
use App\Repository\ItemVersionRepository;
use App\Security\AuthorizationServiceInterface;
use App\Services\Admin\TechnicalBreakServiceInterface;
use App\Services\Audit\AuditLoggerInterface;
use App\Services\Category\CategoryExportServiceInterface;
use App\Services\Item\Collector\ItemGarbageCollectorInterface;
use App\Services\Item\ItemServiceInterface;
use App\Services\Item\StorageLimitServiceInterface;
use App\Services\Item\ValueObject\ItemLifecycleOptions;
use App\Services\Item\ValueObject\ItemListFilter;
use App\Services\Request\RequestServiceInterface;
use DateInterval;
use DateTimeImmutable;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

use function array_map;
use function in_array;
use function is_string;
use function max;
use function min;

/**
 * Every action is ROLE_ADMIN-only — a flat role check via
 * isGranted('ROLE_ADMIN'), not a dedicated Voter, since nothing here is ever
 * "yours" vs. "someone else's" the way CategoryVoter/ItemVoter resources are.
 *
 * `?pouchId=` (nullablePouchId()) narrows storage/gc/audit-log/expiring/
 * backup/items to one pouch at a time; omitting it means "every pouch".
 */
#[OA\Response(response: 401, description: 'User not authorized', content: new Model(type: UnauthorizedExceptionModel::class))]
#[OA\Response(response: 403, description: 'Forbidden — admin only', content: new Model(type: ForbiddenExceptionModel::class))]
#[OA\Tag(name: 'Admin')]
final class AdminController extends AbstractController
{
    use AuthorizesRequestsTrait;
    use ParsesQueryParamsTrait;

    public function __construct(
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly RequestServiceInterface $requestService,
        private readonly ItemRepository $itemRepository,
        private readonly ItemVersionRepository $itemVersionRepository,
        private readonly GcRunLogRepository $gcRunLogRepository,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly StorageLimitServiceInterface $storageLimitService,
        private readonly TechnicalBreakServiceInterface $technicalBreakService,
        private readonly ItemGarbageCollectorInterface $itemGarbageCollector,
        private readonly ItemServiceInterface $itemService,
        private readonly CategoryExportServiceInterface $categoryExportService,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly SerializerInterface $serializer,
        private readonly StreamedFileResponseFactoryInterface $streamedFileResponseFactory,
    ) {}

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/admin/storage', name: 'admin_storage', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'Storage usage per item type, plus archived-version bytes and the current per-type upload '
            . 'size limits (product doc: "podgląd zużycia" + "globalne limity wagowe"). Limits are always '
            . 'system-wide (a single global config, not per pouch); usage narrows to one pouch when pouchId is given.',
        parameters: [new OA\Parameter(name: 'pouchId', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Success', content: new Model(type: StorageReportResponseDTO::class))],
    )]
    public function storage(Request $request): Response
    {
        $this->assertAdmin();

        $pouchId = $this->nullablePouchId($request);
        $responseDTO = new StorageReportResponseDTO(
            byType: AdminMapper::toStorageUsageResponseDTOList($this->itemRepository->sumSizeByType($pouchId)),
            archivedVersionsBytes: $this->itemVersionRepository->sumSize($pouchId),
            limits: AdminMapper::toStorageLimitResponseDTOList($this->storageLimitService->getAllMaxSizeBytes()),
        );

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws BadRequestException           $type isn't "file" or "photo"
     * @throws UnprocessableContentException
     */
    #[Route('/api/admin/storage/limits/{type}', name: 'admin_storage_limit_set', methods: [Request::METHOD_PUT])]
    #[OA\Put(
        description: 'Set the upload size limit (bytes) for one item type — only "file" and "photo" have one',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: StorageLimitSetRequestDTO::class), type: 'object')),
        responses: [new OA\Response(response: 204, description: 'Success')],
    )]
    #[OA\Response(response: 400, description: 'Not a type with a configurable size limit', content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function setStorageLimit(Request $request, string $type): Response
    {
        $this->assertAdmin();

        $itemType = ItemType::tryFrom($type);
        if (null === $itemType || false === in_array($itemType, [ItemType::FILE, ItemType::PHOTO], true)) {
            throw new BadRequestException(message: 'admin.storage_limit_type_invalid');
        }

        $setRequestDTO = $this->requestService->getRequestBodyContent($request, StorageLimitSetRequestDTO::class);
        $this->storageLimitService->setMaxSizeBytes($itemType, $setRequestDTO->getMaxSizeBytes());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/admin/gc/run', name: 'admin_gc_run', methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: '"Run Garbage Collection Now" — the exact same two phases the `app:item:gc` cron runs, '
            . 'narrowed to one pouch when pouchId is given (omit it to sweep every pouch, same as the cron).',
        parameters: [new OA\Parameter(name: 'pouchId', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Success', content: new Model(type: GcRunLogResponseDTO::class))],
    )]
    public function runGc(Request $request): Response
    {
        $this->assertAdmin();

        $runLog = $this->itemGarbageCollector->run(trigger: GcRunLog::TRIGGER_MANUAL, pouchId: $this->nullablePouchId($request));
        $responseDTO = AdminMapper::toGcRunLogResponseDTO($runLog);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/admin/gc/runs', name: 'admin_gc_runs', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'GC run history (newest first) — "podgląd automatycznego czyszczenia"; which items each run '
            . 'actually purged is in the audit log instead (action=purge). pouchId shows only manual runs scoped '
            . "to that pouch — a cron sweep's own row never matches one (see GcRunLogRepository::findLatest()).",
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
            new OA\Parameter(name: 'pouchId', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: GcRunLogResponseDTO::class))),
            ),
        ]
    )]
    public function gcRuns(Request $request): Response
    {
        $this->assertAdmin();

        $limit = $this->clampLimit($request);
        $responseDTO = AdminMapper::toGcRunLogResponseDTOList($this->gcRunLogRepository->findLatest($limit, $this->nullablePouchId($request)));

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/admin/audit-log', name: 'admin_audit_log', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'The audit log (newest first) — "kto, kiedy, z jakiego IP podejrzał/pobrał/usunął/zmienił '
            . 'klucz". See AuditLoggerInterface for exactly which actions are recorded.',
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
            new OA\Parameter(name: 'resourceType', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['category', 'item', 'user'])),
            new OA\Parameter(name: 'action', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['view', 'download', 'delete', 'key_change', 'purge'])),
            new OA\Parameter(name: 'pouchId', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: AuditLogResponseDTO::class))),
            ),
        ]
    )]
    public function auditLog(Request $request): Response
    {
        $this->assertAdmin();

        $limit = $this->clampLimit($request);
        $resourceType = $request->query->get('resourceType');
        $action = $request->query->get('action');

        $entries = $this->auditLogRepository->findLatest(
            $limit,
            is_string($resourceType) ? $resourceType : null,
            is_string($action) ? $action : null,
            $this->nullablePouchId($request),
        );
        $responseDTO = AdminMapper::toAuditLogResponseDTOList($entries);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/admin/items/expiring-soon', name: 'admin_items_expiring_soon', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: '"Lista itemów wygasających w ciągu najbliższych 24h" — the window is configurable, default 24h',
        parameters: [
            new OA\Parameter(name: 'withinHours', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 24)),
            new OA\Parameter(name: 'pouchId', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: ItemResponseDTO::class))),
            ),
        ]
    )]
    public function expiringSoon(Request $request): Response
    {
        $this->assertAdmin();

        $withinHours = max(1, $request->query->getInt('withinHours', 24));
        $now = new DateTimeImmutable();
        $until = $now->add(new DateInterval('PT' . $withinHours . 'H'));

        $responseDTO = ItemMapper::toResponseDTOList($this->itemService->findExpiringBetween($now, $until, $this->nullablePouchId($request)));

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
    #[Route('/api/admin/items/extend', name: 'admin_items_extend', methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: '"Masowe przedłużenie ważności wybranych itemów" — same TTL/keepForever/expiresAt rules as '
            . 'creating an item, applied to a batch of existing ones',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: AdminExtendExpiryRequestDTO::class), type: 'object')),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: ItemResponseDTO::class))),
            ),
        ]
    )]
    #[OA\Response(response: 400, description: "Resulting expiry isn't in the future", content: new Model(type: BadRequestExceptionModel::class))]
    #[OA\Response(response: 404, description: 'One of the items was not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function extendExpiry(Request $request): Response
    {
        $this->assertAdmin();

        $extendRequestDTO = $this->requestService->getRequestBodyContent($request, AdminExtendExpiryRequestDTO::class);

        $options = new ItemLifecycleOptions(
            name: null,
            keepForever: $extendRequestDTO->isKeepForever(),
            ttlPreset: null !== $extendRequestDTO->getTtlPreset() ? TtlPreset::from($extendRequestDTO->getTtlPreset()) : null,
            customExpiresAt: $this->parseCustomExpiresAt($extendRequestDTO->getExpiresAt()),
        );

        $items = $this->itemService->extendExpiry($extendRequestDTO->getItemIds(), $options);
        $responseDTO = ItemMapper::toResponseDTOList($items);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/admin/items', name: 'admin_item_list', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'Browse individual items in one pouch — "zarządzanie itemami/plikami per pouch" (Krok 3), '
            . 'since StoragePage otherwise only shows aggregated byType totals. Unlike GET /api/items, pouchId is '
            . 'required (there is no "current pouch" for an admin browsing someone else\'s) and no access-key '
            . 'locks apply — same "already-authenticated admin" reasoning as CategoryExportServiceInterface::'
            . 'buildFullBackupZip().',
        parameters: [
            new OA\Parameter(name: 'pouchId', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', description: '1-based, defaults to 1', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pageSize', description: 'Defaults to 24, capped at 200', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new Model(type: ItemListResponseDTO::class)),
        ]
    )]
    #[OA\Response(response: 400, description: 'pouchId is missing', content: new Model(type: BadRequestExceptionModel::class))]
    public function items(Request $request): Response
    {
        $this->assertAdmin();

        $pouchId = $this->nullablePouchId($request);
        if (null === $pouchId) {
            throw new BadRequestException(message: 'admin.pouch_id_required');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $pageSize = min(ItemListPageSize::MAX->value, max(1, $request->query->getInt('pageSize', ItemListPageSize::DEFAULT->value)));

        $result = $this->itemRepository->findFilteredPage(new ItemListFilter(), ($page - 1) * $pageSize, $pageSize, pouchId: $pouchId);

        $responseDTO = new ItemListResponseDTO(
            items: array_map(ItemMapper::toSummaryResponseDTO(...), $result['items']),
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
     */
    #[Route('/api/admin/items/{id}', name: 'admin_item_delete', requirements: ['id' => '\d+'], methods: [Request::METHOD_DELETE])]
    #[OA\Delete(
        description: 'Move any item, in any pouch, to the trash — unlike DELETE /api/items/{id} (scoped to the '
            . "caller's own pouch), this is the admin's cross-pouch counterpart backing the item browser above.",
        responses: [new OA\Response(response: 204, description: 'Trashed')],
    )]
    #[OA\Response(response: 404, description: 'Item not found', content: new Model(type: NotFoundExceptionModel::class))]
    public function deleteItem(Request $request, int $id): Response
    {
        $user = $this->assertAdmin();

        $item = $this->itemService->getById($id);
        $this->itemService->deleteAsAdmin($id);
        $this->auditLogger->log(AuditLoggerInterface::ACTION_DELETE, AuditLoggerInterface::RESOURCE_ITEM, $id, $user, $request, $item->getPouch());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     */
    #[Route('/api/admin/backup', name: 'admin_backup', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: '"Eksport/backup całości jako ZIP" — every category tree in one archive (see '
            . 'CategoryExportServiceInterface::buildFullBackupZip()), narrowed to one pouch when pouchId is given.',
        parameters: [new OA\Parameter(name: 'pouchId', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'The archive, streamed')],
    )]
    public function backup(Request $request): StreamedResponse
    {
        $this->assertAdmin();

        $zipPath = $this->categoryExportService->buildFullBackupZip($request, $this->nullablePouchId($request));

        return $this->streamedFileResponseFactory->fromTemporaryFile(
            localPath: $zipPath,
            downloadName: 'pouch-backup-' . new DateTimeImmutable()->format('Y-m-d') . '.zip',
            contentType: 'application/zip',
        );
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/admin/technical-break', name: 'admin_technical_break_status', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'Current technical-break state — see TechnicalBreakListener for what an active break does to non-admin requests.',
        responses: [new OA\Response(response: 200, description: 'Success', content: new Model(type: TechnicalBreakResponseDTO::class))],
    )]
    public function technicalBreakStatus(): Response
    {
        $this->assertAdmin();

        $responseDTO = AdminMapper::toTechnicalBreakResponseDTO($this->technicalBreakService->getActive());

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws SerializerExceptionInterface
     * @throws UnprocessableContentException
     */
    #[Route('/api/admin/technical-break', name: 'admin_technical_break_enable', methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Enables the technical break — every logged-in non-admin request gets 503 (TechnicalBreakException) '
            . "until it's disabled again. Admins are never blocked, on any endpoint, including this one.",
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: TechnicalBreakEnableRequestDTO::class), type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Success', content: new Model(type: TechnicalBreakResponseDTO::class))],
    )]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function enableTechnicalBreak(Request $request): Response
    {
        $user = $this->assertAdmin();

        $enableRequestDTO = $this->requestService->getRequestBodyContent($request, TechnicalBreakEnableRequestDTO::class);
        $technicalBreak = $this->technicalBreakService->enable($enableRequestDTO->getMessage());
        $this->auditLogger->log(AuditLoggerInterface::ACTION_ENABLE, AuditLoggerInterface::RESOURCE_TECHNICAL_BREAK, $technicalBreak->getId(), $user, $request);

        $responseDTO = AdminMapper::toTechnicalBreakResponseDTO($technicalBreak);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     */
    #[Route('/api/admin/technical-break', name: 'admin_technical_break_disable', methods: [Request::METHOD_DELETE])]
    #[OA\Delete(
        description: 'Disables the technical break, if one is active. A no-op (no audit entry) if none is.',
        responses: [new OA\Response(response: 204, description: 'Success')],
    )]
    public function disableTechnicalBreak(Request $request): Response
    {
        $user = $this->assertAdmin();

        $technicalBreak = $this->technicalBreakService->disable();
        if (null !== $technicalBreak) {
            $this->auditLogger->log(AuditLoggerInterface::ACTION_DISABLE, AuditLoggerInterface::RESOURCE_TECHNICAL_BREAK, $technicalBreak->getId(), $user, $request);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
