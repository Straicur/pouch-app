<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\ControllerHelper\Traits\AuthorizesRequestsTrait;
use App\ControllerHelper\Traits\ParsesCommaSeparatedValuesTrait;
use App\DTO\Mapper\ItemMapper;
use App\DTO\Response\ItemListResponseDTO;
use App\DTO\Response\ItemResponseDTO;
use App\DTO\Response\ItemSummaryResponseDTO;
use App\Entity\Item;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedExceptionModel;
use App\Security\AccessKey\AccessKeyGuardInterface;
use App\Security\AuthorizationServiceInterface;
use App\Security\Voter\ItemVoter;
use App\Services\Audit\AuditLoggerInterface;
use App\Services\Item\ItemServiceInterface;
use App\Services\Item\ValueObject\ItemListFilter;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

use function array_filter;
use function array_map;
use function array_values;
use function max;
use function min;
use function trim;

/**
 * The item resource's core CRUD (list/get/delete) — creation lives in
 * ItemCreateController, editing (note/tags/favorite/move/overwrite/versions)
 * in ItemEditController, and downloads/signed links/public sharing in
 * ItemDeliveryController. Split along those lines once this file passed
 * 1000 lines (project-rules.md's per-file limit) — same
 * auth-then-voter-then-AccessKeyGuard pattern applies in every one of them,
 * see each controller's own class docblock for the parts specific to it.
 *
 * Every mutating/metadata action checks auth first (401), then ItemVoter
 * (403) — same pattern as CategoryController. After that, every action
 * touching a specific item or category also checks AccessKeyGuard (Part 7):
 * a locked category/item without a valid grant on the request either 403s
 * or, for list(), is just filtered out.
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
    use ParsesCommaSeparatedValuesTrait;

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
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly ItemServiceInterface $itemService,
        private readonly AccessKeyGuardInterface $accessKeyGuard,
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
}
