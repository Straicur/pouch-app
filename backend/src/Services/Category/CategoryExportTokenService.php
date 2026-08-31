<?php

declare(strict_types = 1);

namespace App\Services\Category;

use App\DTO\Response\CategoryExportTokenResponseDTO;
use App\Security\AccessKey\AccessKeyGuardInterface;
use App\Security\SignedUrlServiceInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Symfony\Component\HttpFoundation\Request;

use function base64_decode;
use function base64_encode;
use function hash;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;

final readonly class CategoryExportTokenService implements CategoryExportTokenServiceInterface
{
    private const int TOKEN_TTL_SECONDS = 60;

    public function __construct(
        private SignedUrlServiceInterface $signedUrlService,
    ) {}

    #[Override]
    public function issue(int $categoryId, int $userId, Request $request): CategoryExportTokenResponseDTO
    {
        $grantsJson = $request->headers->get(AccessKeyGuardInterface::GRANTS_HEADER) ?? '[]';
        $resource = $this->resource($categoryId, $userId, $grantsJson);
        $signed = $this->signedUrlService->sign($resource, self::TOKEN_TTL_SECONDS);

        $token = base64_encode((string) json_encode([
            'grants'    => $grantsJson,
            'resource'  => $resource,
            'expires'   => $signed['expires'],
            'signature' => $signed['signature'],
        ]));

        return new CategoryExportTokenResponseDTO(
            token: $token,
            expiresAt: new DateTimeImmutable('@' . $signed['expires'])->format(DateTimeInterface::ATOM),
        );
    }

    #[Override]
    public function apply(Request $request, int $categoryId, int $userId): void
    {
        $token = $request->query->get('token');
        if (null === $token || '' === $token) {
            return;
        }

        $decoded = json_decode(base64_decode($token, true) ?: '', true);
        if (false === is_array($decoded)) {
            return;
        }

        $grantsJson = $decoded['grants'] ?? null;
        $resource = $decoded['resource'] ?? null;
        $expires = $decoded['expires'] ?? null;
        $signature = $decoded['signature'] ?? null;

        if (in_array(false, [is_string($grantsJson), is_string($resource), is_int($expires), is_string($signature)], true)) {
            return;
        }

        // The resource embeds a hash of $grantsJson — a token can't be
        // replayed with substituted grants, or against a different category/
        // user than it was minted for.
        if ($this->resource($categoryId, $userId, $grantsJson) !== $resource) {
            return;
        }

        if (false === $this->signedUrlService->isValid($resource, $expires, $signature)) {
            return;
        }

        $request->headers->set(AccessKeyGuardInterface::GRANTS_HEADER, $grantsJson);
    }

    private function resource(int $categoryId, int $userId, string $grantsJson): string
    {
        return 'category-export:' . $categoryId . ':u' . $userId . ':' . hash('sha256', $grantsJson);
    }
}
