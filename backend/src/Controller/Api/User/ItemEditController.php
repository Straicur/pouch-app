<?php

declare(strict_types = 1);

namespace App\Controller\Api\User;

use App\ControllerHelper\Traits\AuthorizesRequestsTrait;
use App\ControllerHelper\Traits\ExtractsUploadedFileTrait;
use App\DTO\Mapper\ItemMapper;
use App\DTO\Request\ItemMoveRequestDTO;
use App\DTO\Request\ItemUpdateNoteRequestDTO;
use App\DTO\Request\ItemUpdateTagsRequestDTO;
use App\DTO\Response\ItemResponseDTO;
use App\DTO\Response\ItemVersionResponseDTO;
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
 * Editing an existing item — note content, category (move), tags, favorite,
 * file content (overwrite, versioning) and its version history. See
 * ItemController's own class docblock for how this file relates to the rest
 * of the item resource's controllers.
 */
#[OA\Response(response: 401, description: 'User not authorized', content: new Model(type: UnauthorizedExceptionModel::class))]
#[OA\Response(response: 403, description: 'Forbidden — logged in, but role doesn\'t allow this action', content: new Model(type: ForbiddenExceptionModel::class))]
#[OA\Tag(name: 'Item')]
final class ItemEditController extends AbstractController
{
    use AuthorizesRequestsTrait;
    use ExtractsUploadedFileTrait;

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
     * @throws NotFoundException             if the item or the target category doesn't exist
     * @throws UnprocessableContentException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/items/{id}/move', name: 'item_move', requirements: ['id' => '\d+'], methods: [Request::METHOD_PATCH])]
    #[OA\Patch(
        description: 'Move an item to a different category',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: ItemMoveRequestDTO::class), type: 'object'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new Model(type: ItemResponseDTO::class),
            ),
        ]
    )]
    #[OA\Response(response: 404, description: 'Item or target category not found', content: new Model(type: NotFoundExceptionModel::class))]
    #[OA\Response(response: 422, description: 'Unprocessable Content', content: new Model(type: UnprocessableContentExceptionModel::class))]
    public function move(Request $request, int $id): Response
    {
        $this->assertGranted(ItemVoter::EDIT);

        $moveRequestDTO = $this->requestService->getRequestBodyContent($request, ItemMoveRequestDTO::class);
        $this->accessKeyGuard->assertItemUnlocked($this->itemService->getById($id), $request);
        $this->accessKeyGuard->assertCategoryUnlocked($this->categoryService->getById($moveRequestDTO->getCategoryId()), $request);

        $item = $this->itemService->move($id, $moveRequestDTO->getCategoryId());

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
}
