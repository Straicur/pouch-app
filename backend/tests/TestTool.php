<?php

declare(strict_types=1);

namespace App\Tests;

use App\ExceptionManagement\Exceptions\ExceptionUuidEnum;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class TestTool extends TestCase
{
    public function testBadRequestResponseData(KernelBrowser $webClient): void
    {
        $responseContent= $this->assertAndGetResponseContent(
            $webClient->getResponse()->getContent()
        );

        $this->assertResponseContentHasRequiredKeys($responseContent);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $responseContent['status']);
        $this->assertSame($responseContent['context']['uuid'], ExceptionUuidEnum::BAD_REQUEST->value);
    }

    public function testForbiddenRequestResponseData(KernelBrowser $webClient): void
    {
        $responseContent= $this->assertAndGetResponseContent(
            $webClient->getResponse()->getContent()
        );

        $this->assertResponseContentHasRequiredKeys($responseContent);
        $this->assertSame(Response::HTTP_FORBIDDEN, $responseContent['status']);
        $this->assertSame($responseContent['context']['uuid'], ExceptionUuidEnum::FROBIDDEN->value);
    }

    public function testNotFoundRequestResponseData(KernelBrowser $webClient): void
    {
        $responseContent= $this->assertAndGetResponseContent(
            $webClient->getResponse()->getContent()
        );

        $this->assertResponseContentHasRequiredKeys($responseContent);
        $this->assertSame(Response::HTTP_NOT_FOUND, $responseContent['status']);
        $this->assertSame($responseContent['context']['uuid'], ExceptionUuidEnum::NOT_FOUND->value);
    }

    public function testUnauthorizedRequestResponseData(KernelBrowser $webClient): void
    {
        $responseContent= $this->assertAndGetResponseContent(
            $webClient->getResponse()->getContent()
        );

        $this->assertResponseContentHasRequiredKeys($responseContent);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $responseContent['status']);
        $this->assertSame($responseContent['context']['uuid'], ExceptionUuidEnum::UNAUTHORIZED->value);
    }

    public function testUnprocessableContentRequestResponseData(KernelBrowser $webClient): void
    {
        $responseContent= $this->assertAndGetResponseContent(
            $webClient->getResponse()->getContent()
        );

        $this->assertResponseContentHasRequiredKeys($responseContent);
        $this->assertArrayHasKey('violations', $responseContent);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $responseContent['status']);
        $this->assertSame($responseContent['context']['uuid'], ExceptionUuidEnum::UNPROCESSABLE_CONTENT->value);
    }

    public function testInternalServerRequestResponseData(KernelBrowser $webClient): void
    {
        $responseContent= $this->assertAndGetResponseContent(
            $webClient->getResponse()->getContent()
        );

        $this->assertResponseContentHasRequiredKeys($responseContent);
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $responseContent['status']);
        $this->assertSame($responseContent['context']['uuid'], ExceptionUuidEnum::INTERNAL_SERVER->value);
    }

    private function assertAndGetResponseContent(false|string $responseContent): array
    {
        $this->assertIsString($responseContent);
        $this->assertNotEmpty($responseContent);
        $this->assertJson($responseContent);

        return json_decode($responseContent, true);
    }

    private function assertResponseContentHasRequiredKeys(array $responseContent):void
    {
        $this->assertIsArray($responseContent);
        $this->assertArrayHasKey('status', $responseContent);
        $this->assertArrayHasKey('title', $responseContent);
        $this->assertArrayHasKey('detail', $responseContent);
        $this->assertArrayHasKey('context', $responseContent);
    }
}
