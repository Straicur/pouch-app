<?php

declare(strict_types = 1);

namespace App\ExceptionManagement;

use App\ExceptionManagement\Exceptions\ApiException;
use App\ExceptionManagement\Exceptions\Model\ExceptionModel;
use App\ExceptionManagement\Exceptions\ServerException;
use App\ExceptionManagement\Exceptions\ServerException\InternalServerException\InternalServerException;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Every ApiException/ServerException's `detail` is a translation key (see
 * translations/exceptions.pl.yaml) unless the throw site already produced a
 * final human string itself (e.g. one with per-request interpolated values,
 * such as ItemService's duplicate-content message) — trans() on a string
 * that isn't a known key just returns it unchanged, so both cases work
 * through the same call here without the model needing to say which.
 */
class ExceptionSubscriber implements EventSubscriberInterface
{
    private const string DOMAIN = 'exceptions';

    /**
     * Framework-thrown HttpExceptionInterface exceptions (unmatched route ->
     * 404, wrong method -> 405, etc.) never go through our own hierarchy, so
     * without this map they'd fall into the catch-all below and come back as
     * a misleading 500.
     *
     * @var array<int, string>
     */
    private const array STATUS_TRANSLATION_KEYS = [
        Response::HTTP_BAD_REQUEST        => 'bad_request',
        Response::HTTP_UNAUTHORIZED       => 'unauthorized',
        Response::HTTP_FORBIDDEN          => 'forbidden',
        Response::HTTP_NOT_FOUND          => 'not_found',
        Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
        Response::HTTP_TOO_MANY_REQUESTS  => 'too_many_requests',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
        private readonly TranslatorInterface $translator,
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
            $model = $exception->getModel();
            $model->setDetail($this->translator->trans($model->getDetail(), domain: self::DOMAIN));

            return new Response(
                content: $this->serializer->serialize(
                    data: $model,
                    format: JsonEncoder::FORMAT
                ),
                status: $exception->getCode()
            );
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $key = self::STATUS_TRANSLATION_KEYS[$status] ?? 'bad_request';
            $model = new ExceptionModel(
                title: Response::$statusTexts[$status] ?? 'Error',
                status: $status,
                detail: $this->translator->trans($key, domain: self::DOMAIN),
            );

            return new Response(
                content: $this->serializer->serialize(data: $model, format: JsonEncoder::FORMAT),
                status: $status,
            );
        }

        $this->logger->error($exception->getMessage());

        $serverError = new InternalServerException();
        $serverErrorModel = $serverError->getModel();
        $serverErrorModel->setDetail($this->translator->trans($serverErrorModel->getDetail(), domain: self::DOMAIN));

        return new Response(
            content: $this->serializer->serialize(
                data: $serverErrorModel,
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
