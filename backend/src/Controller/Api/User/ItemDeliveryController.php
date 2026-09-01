<?php

declare(strict_types = 1);

namespace App\Controller\Api\User;

use App\ControllerHelper\Traits\AuthorizesRequestsTrait;
use App\DTO\Mapper\ItemMapper;
use App\DTO\Response\DownloadLinkResponseDTO;
use App\DTO\Response\PublicItemLinkResponseDTO;
use App\DTO\Response\PublicItemResponseDTO;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedExceptionModel;
use App\Security\AccessKey\AccessKeyGuardInterface;
use App\Security\AuthorizationServiceInterface;
use App\Security\ConfigServiceInterface;
use App\Security\SignedUrlServiceInterface;
use App\Security\Voter\ItemVoter;
use App\Services\Audit\AuditLoggerInterface;
use App\Services\Item\ItemServiceInterface;
use App\Services\Storage\StorageServiceInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
use function stream_copy_to_stream;

/**
 * Downloads, thumbnails, version downloads and public sharing — every
 * "give me the actual bytes" action for an item, plus the signed-link
 * endpoints that authorize them. See ItemController's own class docblock for
 * how this file relates to the rest of the item resource's controllers.
 *
 * The `...Link()`/`public*()` actions that *mint* a link go through the
 * normal auth/voter/AccessKeyGuard check, same as everywhere else. The
 * actions that actually stream bytes — download()/thumbnail()/
 * versionDownload()/publicView() — deliberately don't: a valid signed URL is
 * its own, separate authorization channel, so the key check already
 * happened when the signed link was generated. publicView() (and the
 * file/thumbnail links publicLink() mints alongside it) additionally uses a
 * much longer-lived signature since it's meant to be opened by someone with
 * no account at all, not fetched immediately by the app that just requested it.
 */
#[OA\Response(response: 401, description: 'User not authorized', content: new Model(type: UnauthorizedExceptionModel::class))]
#[OA\Response(response: 403, description: 'Forbidden — logged in, but role doesn\'t allow this action', content: new Model(type: ForbiddenExceptionModel::class))]
#[OA\Tag(name: 'Item')]
final class ItemDeliveryController extends AbstractController
{
    use AuthorizesRequestsTrait;

    private const int LINK_TTL_SECONDS = 900;

    /**
     * Much longer than the 15-minute private preview links above, since a
     * public link is meant to be handed to someone else to use whenever they
     * get to it, not fetched immediately by the app that just requested it.
     */
    private const int PUBLIC_LINK_TTL_SECONDS = 86_400;

    public function __construct(
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly ItemServiceInterface $itemService,
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

        // No $user — this endpoint takes no auth token by design (see the class docblock).
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
        // The genuinely-external public links use publicSignedUrl() below
        // instead, precisely because they need a real absolute URL.
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
     * Counterpart to signedLinkResponse() above — same signing, but a
     * genuinely absolute URL (ConfigService::getPublicBaseUrl(), not this
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
}
