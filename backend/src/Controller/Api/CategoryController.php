<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\ControllerHelper\Traits\AuthorizesRequestsTrait;
use App\ControllerHelper\Factory\StreamedFileResponseFactoryInterface;
use App\Services\Audit\AuditLoggerInterface;
use App\Services\Category\CategoryExportServiceInterface;
use App\Services\Category\CategoryServiceInterface;
use App\DTO\Mapper\CategoryMapper;
use App\DTO\Request\CategoryCreateRequestDTO;
use App\DTO\Request\CategoryMoveRequestDTO;
use App\DTO\Request\CategoryRenameRequestDTO;
use App\DTO\Response\CategoryExportTokenResponseDTO;
use App\DTO\Response\CategoryResponseDTO;
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
use App\Security\SignedUrlServiceInterface;
use App\Security\Voter\CategoryVoter;
use App\Services\Request\RequestServiceInterface;
use DateTimeImmutable;
use DateTimeInterface;
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

use function base64_decode;
use function base64_encode;
use function hash;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;

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
    use AuthorizesRequestsTrait;

    public function __construct(
        private readonly RequestServiceInterface $requestService,
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly CategoryServiceInterface $categoryService,
        private readonly CategoryExportServiceInterface $categoryExportService,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly SerializerInterface $serializer,
        private readonly SignedUrlServiceInterface $signedUrlService,
        private readonly StreamedFileResponseFactoryInterface $streamedFileResponseFactory,
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
        $this->assertGranted(CategoryVoter::VIEW);

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
        $this->assertGranted(CategoryVoter::CREATE);

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
        $this->assertGranted(CategoryVoter::RENAME);

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
        $this->assertGranted(CategoryVoter::MOVE);

        $moveRequestDTO = $this->requestService->getRequestBodyContent($request, CategoryMoveRequestDTO::class);

        $category = $this->categoryService->move(id: $id, parentId: $moveRequestDTO->getParentId());

        $responseDTO = CategoryMapper::toResponseDTO($category);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws ConflictException
     */
    #[Route('/api/categories/{id}', name: 'category_delete', requirements: ['id' => '\d+'], methods: [Request::METHOD_DELETE])]
    #[OA\Delete(
        description: 'Delete a category (admin only) — cascades to its descendants. Refused (409) while it or any '
            . 'descendant still holds an active item — trash/move those first.',
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
    #[OA\Response(
        response: 409,
        description: 'The category or one of its descendants still holds an active item',
        content: new Model(type: ConflictExceptionModel::class)
    )]
    public function delete(Request $request, int $id): Response
    {
        $user = $this->assertGranted(CategoryVoter::DELETE);

        $this->categoryService->delete($id);
        $this->auditLogger->log(AuditLoggerInterface::ACTION_DELETE, AuditLoggerInterface::RESOURCE_CATEGORY, $id, $user, $request);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Post-review fix: mints the short-lived (EXPORT_TOKEN_TTL_SECONDS)
     * token export()/backup() expect on "?token=" — see that param's own doc
     * comment for why this replaced relaying grants directly in the URL.
     * Called as a normal AJAX POST (not a navigation), so it can set the
     * usual X-Pouch-Access-Grants header without issue; the token it returns
     * is what then goes on the actual download link.
     *
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/categories/{id}/export-token', name: 'category_export_token', requirements: ['id' => '\d+'], methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Mints a short-lived, one-purpose token authorizing GET .../export to see whatever this '
            . 'request currently has valid access-key grants for — the actual download then happens via a plain '
            . 'navigation, which can\'t carry the X-Pouch-Access-Grants header itself.',
        responses: [
            new OA\Response(response: 200, description: 'Success', content: new Model(type: CategoryExportTokenResponseDTO::class)),
        ]
    )]
    #[OA\Response(
        response: 404,
        description: 'Category not found',
        content: new Model(type: NotFoundExceptionModel::class)
    )]
    public function exportToken(Request $request, int $id): Response
    {
        $user = $this->assertGranted(CategoryVoter::VIEW);

        // Exists, purely to 404 early on a bad id rather than mint a token
        // for an export that's guaranteed to fail once actually requested.
        $this->categoryService->getById($id);

        $responseDTO = $this->issueExportToken($id, $user->getId(), $request);

        return new Response($this->serializer->serialize(data: $responseDTO, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
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
        description: 'Download a category (and its full subtree) as a ZIP, preserving folder structure. Pass a '
            . '"token" from POST .../export-token to include whatever this session currently has access-key '
            . 'grants for — omit it to get everything this account can see *without* a key, same as before.',
        parameters: [
            new OA\Parameter(name: 'token', description: 'A token from POST .../export-token', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
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
        $user = $this->assertGranted(CategoryVoter::VIEW);

        $this->applyExportToken($request, $id, $user->getId());

        $category = $this->categoryService->getById($id);
        $zipPath = $this->categoryExportService->buildZip($id, $request);

        return $this->streamedFileResponseFactory->fromTemporaryFile(
            localPath: $zipPath,
            downloadName: $category->getName() . '.zip',
            contentType: 'application/zip',
        );
    }

    /**
     * Post-review fix: a plain navigation/download link (frontend's
     * triggerDownload.ts, used so the ZIP streams instead of being buffered
     * as a Blob) can't set the X-Pouch-Access-Grants header, so a grant
     * earned earlier in the session was silently invisible to export() and
     * CategoryExportService quietly skipped every locked item as if nothing
     * had ever been unlocked.
     *
     * An earlier version of this fix relayed the grants themselves on a
     * "?grants=" query parameter — technically no worse than the header
     * they already travel on (each one's individually signed and scoped),
     * but still real content sitting in browser history and proxy access
     * logs, and unbounded in size (enough grants and the URL trips a
     * request-line length limit). This mints a fixed-size, opaque,
     * short-lived (EXPORT_TOKEN_TTL_SECONDS) token instead — the grants
     * still ride inside it (HMAC-signed, not encrypted: they're the
     * requesting user's own, not a secret from them), but the *token* is
     * all that ever appears in the URL/logs/history, and it's worthless
     * again a minute later.
     */
    private const int EXPORT_TOKEN_TTL_SECONDS = 60;

    private function issueExportToken(int $categoryId, int $userId, Request $request): CategoryExportTokenResponseDTO
    {
        $grantsJson = $request->headers->get(AccessKeyGuardInterface::GRANTS_HEADER) ?? '[]';
        $resource = $this->exportTokenResource($categoryId, $userId, $grantsJson);
        $signed = $this->signedUrlService->sign($resource, self::EXPORT_TOKEN_TTL_SECONDS);

        $token = base64_encode((string) json_encode([
            'grants'    => $grantsJson,
            'resource'  => $resource,
            'expires'   => $signed['expires'],
            'signature' => $signed['signature'],
        ]));

        return new CategoryExportTokenResponseDTO(
            token: $token,
            expiresAt: new DateTimeImmutable('@' . $signed['expires'])->format(DateTimeInterface::ATOM),
        );
    }

    /**
     * Decodes/verifies "?token=" (silently a no-op if it's missing, expired,
     * tampered with, or was minted for a different category/user — same
     * "just export without whatever grant should've applied" leniency
     * export() already had for a missing/invalid grant) and, if valid,
     * applies the grants it carries the same way the header normally would.
     */
    private function applyExportToken(Request $request, int $categoryId, int $userId): void
    {
        $token = $request->query->get('token');
        if (null === $token || '' === $token) {
            return;
        }

        $decoded = json_decode(base64_decode($token, true) ?: '', true);
        if (false === is_array($decoded)) {
            return;
        }

        $grantsJson = $decoded['grants'] ?? null;
        $resource = $decoded['resource'] ?? null;
        $expires = $decoded['expires'] ?? null;
        $signature = $decoded['signature'] ?? null;

        if (false === is_string($grantsJson) || false === is_string($resource) || false === is_int($expires) || false === is_string($signature)) {
            return;
        }

        // The resource embeds a hash of $grantsJson — a token can't be
        // replayed with substituted grants, or against a different category/
        // user than it was minted for.
        if ($resource !== $this->exportTokenResource($categoryId, $userId, $grantsJson)) {
            return;
        }

        if (false === $this->signedUrlService->isValid($resource, $expires, $signature)) {
            return;
        }

        $request->headers->set(AccessKeyGuardInterface::GRANTS_HEADER, $grantsJson);
    }

    private function exportTokenResource(int $categoryId, int $userId, string $grantsJson): string
    {
        return 'category-export:' . $categoryId . ':u' . $userId . ':' . hash('sha256', $grantsJson);
    }
}
