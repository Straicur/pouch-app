<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\ControllerHelper\Traits\AuthorizesRequestsTrait;
use App\Entity\Tag;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedExceptionModel;
use App\Security\AuthorizationServiceInterface;
use App\Security\Voter\ItemVoter;
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
 * Read-only — tags themselves have no CRUD of their own (see ItemController's
 * PUT /api/items/{id}/tags), just this list for the frontend's tag-filter/
 * autocomplete UI. Gated the same as items (ItemVoter::VIEW, ROLE_GUEST):
 * tag names aren't sensitive, but they are only meaningful in the context of
 * items a guest can already see.
 */
#[OA\Response(response: 401, description: 'User not authorized', content: new Model(type: UnauthorizedExceptionModel::class))]
#[OA\Response(response: 403, description: 'Forbidden', content: new Model(type: ForbiddenExceptionModel::class))]
#[OA\Tag(name: 'Tag')]
final class TagController extends AbstractController
{
    use AuthorizesRequestsTrait;

    public function __construct(
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly TagServiceInterface $tagService,
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
            $this->tagService->listAll(),
        );

        return new Response($this->serializer->serialize(data: $names, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }
}
