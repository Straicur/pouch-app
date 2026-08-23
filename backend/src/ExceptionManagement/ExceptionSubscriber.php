<?php

declare(strict_types = 1);

namespace App\ExceptionManagement;

use App\ExceptionManagement\Exceptions\ApiException;
use App\ExceptionManagement\Exceptions\ServerException;
use App\ExceptionManagement\Exceptions\ServerException\InternalServerException\InternalServerException;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $event->setResponse($this->getResponseFromException($exception));
    }

    private function getResponseFromException(Throwable $exception): Response
    {
        if ($exception instanceof ApiException
            || $exception instanceof ServerException
        ) {
            return new Response(
                content: $this->serializer->serialize(
                    data: $exception->getModel(),
                    format: JsonEncoder::FORMAT
                ),
                status: $exception->getCode()
            );
        }

        $this->logger->error($exception->getMessage());

        $serverError = new InternalServerException();

        return new Response(
            content: $this->serializer->serialize(
                data: $serverError->getModel(),
                format: JsonEncoder::FORMAT
            ),
            status: $serverError->getCode()
        );
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
