<?php

declare(strict_types = 1);

namespace App\Service;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException\UnprocessableContentException;
use App\ExceptionManagement\Exceptions\ApiException\UnprocessableContentException\ViolationModel;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RequestService implements RequestServiceInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger,
    ) {}

    #[Override]
    public function getRequestBodyContent(Request $request, string $className): object
    {
        $bodyContent = $request->getContent();

        try {
            $query = $this->serializer->deserialize($bodyContent, $className, JsonEncoder::FORMAT);
        } catch (SerializerExceptionInterface $exception) {
            $this->logger->error($exception->getMessage());
            throw new BadRequestException();
        }

        if (false === $query instanceof $className) {
            throw new BadRequestException();
        }

        $validationErrors = $this->validator->validate($query);
        if ($validationErrors->count() > 0) {
            $validationErrorModels = [];

            foreach ($validationErrors as $validationError) {
                $validationErrorModels[] = new ViolationModel(
                    propertyPath: $validationError->getPropertyPath(),
                    message: (string) $validationError->getMessage(),
                    code: $validationError->getCode(),
                );
            }

            throw new UnprocessableContentException(violations: $validationErrorModels);
        }

        return $query;
    }
}
