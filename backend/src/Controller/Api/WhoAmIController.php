<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\DTO\Response\WhoAmIResponseDTO;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedExceptionModel;
use App\Security\AuthServiceInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[OA\Response(
    response: 401,
    description: 'User not authorized',
    content: new Model(type: UnauthorizedExceptionModel::class)
)]
#[OA\Tag(name: 'Auth')]
final class WhoAmIController extends AbstractController
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly SerializerInterface $serializer,
    ) {}

    /**
     * @throws UnauthorizedException
     * @throws SerializerExceptionInterface
     */
    #[Route('/api/whoami', name: 'whoami', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'Returns the currently logged-in user — used by the frontend to check whether a session is '
            . 'still valid.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new Model(type: WhoAmIResponseDTO::class),
            ),
        ]
    )]
    public function whoAmI(): Response
    {
        $user = $this->authService->getUserFromAccessToken();

        $response = new WhoAmIResponseDTO(email: $user->getEmail());

        return new Response($this->serializer->serialize(data: $response, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }
}
