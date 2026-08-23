<?php

declare(strict_types = 1);

namespace App\Controller\Api;

use App\DTO\Request\TestRequestDTO;
use App\DTO\Response\TestResponseDTO;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedException;
use App\ExceptionManagement\Exceptions\ApiException\UnauthorizedException\UnauthorizedExceptionModel;
use App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException\UnprocessableContentException;
use App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException\UnprocessableContentExceptionModel;
use App\Security\AuthServiceInterface;
use App\Service\RequestServiceInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[OA\Response(
    response: 400,
    description: 'JSON Data Invalid',
    content: new Model(type: BadRequestExceptionModel::class)
)]
#[OA\Response(
    response: 401,
    description: 'User not authorized',
    content: new Model(type: UnauthorizedExceptionModel::class)
)]
#[OA\Response(
    response: 422,
    description: 'Unprocessable Content',
    content: new Model(type: UnprocessableContentExceptionModel::class)
)]
#[OA\Tag(name: 'Test')]
final class TestController extends AbstractController
{
    public function __construct(
        private readonly RequestServiceInterface $requestService,
        private readonly AuthServiceInterface $authService,
        private readonly SerializerInterface $serializer,
    ) {}

    /**
     * @throws BadRequestException
     * @throws UnauthorizedException
     * @throws UnprocessableContentException
     * @throws ExceptionInterface
     */
    #[Route('/api/test', name: 'test', methods: [Request::METHOD_POST])]
    #[OA\Post(
        description: 'Test endpoint',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: new Model(type: TestRequestDTO::class),
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new Model(type: TestResponseDTO::class),
            ),
        ]
    )]
    public function test(
        Request $request,
    ): Response {
        $this->requestService->getRequestBodyContent($request, TestRequestDTO::class);

        $user = $this->authService->getUserFromAccessToken();

        $response = new TestRequestDTO(
            email: $user->getEmail(),
        );

        return new Response($this->serializer->serialize(data: $response, format: JsonEncoder::FORMAT), status: Response::HTTP_OK);
    }
}
