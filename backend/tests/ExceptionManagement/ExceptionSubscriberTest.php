<?php

declare(strict_types = 1);

namespace App\Tests\ExceptionManagement;

use App\ExceptionManagement\Exceptions\ExceptionUuidEnum;
use App\Tests\WebTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exceptions the framework throws before ever reaching our own hierarchy
 * (unmatched route, wrong HTTP method) — see TranslationTest for the
 * translator in isolation and CategoryControllerTest/ItemControllerTest for
 * our own ApiException hierarchy going through the same envelope.
 */
class ExceptionSubscriberTest extends WebTest
{
    public function testUnknownRouteReturns404NotAMisleading500(): void
    {
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/definitely-missing');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $content = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame('Nie znaleziono zasobu.', $content['detail']);
        // FRONTEND.md's contract: every endpoint's error carries
        // context.uuid, including ones the framework throws before ever
        // reaching our own exception hierarchy.
        self::assertSame(ExceptionUuidEnum::NOT_FOUND->value, $content['context']['uuid']);
    }

    public function testWrongHttpMethodReturns405WithTranslatedDetail(): void
    {
        // /api/logout only accepts POST.
        $this->webClient->request(method: Request::METHOD_GET, uri: '/api/logout');

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);

        $content = json_decode((string) $this->webClient->getResponse()->getContent(), true);
        self::assertSame('Ta metoda HTTP nie jest dozwolona dla tego adresu.', $content['detail']);
        self::assertSame(ExceptionUuidEnum::METHOD_NOT_ALLOWED->value, $content['context']['uuid']);
    }
}
