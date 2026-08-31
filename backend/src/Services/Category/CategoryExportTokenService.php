<?php

declare(strict_types = 1);

namespace App\Services\Category;

use App\DTO\Response\CategoryExportTokenResponseDTO;
use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use App\Security\AccessKey\AccessKeyGuardInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;

use function bin2hex;
use function is_array;
use function is_string;
use function random_bytes;

final readonly class CategoryExportTokenService implements CategoryExportTokenServiceInterface
{
    private const int TOKEN_TTL_SECONDS = 60;

    private const string CACHE_KEY_PREFIX = 'category_export_token.';

    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {}

    #[Override]
    public function issue(int $categoryId, int $userId, Request $request): CategoryExportTokenResponseDTO
    {
        $grantsJson = $request->headers->get(AccessKeyGuardInterface::GRANTS_HEADER) ?? '[]';

        // A random, fixed-size opaque handle — unlike the previous
        // base64(json(...)) shape, its size doesn't grow with the number of
        // grants, and it carries nothing decodable at all: the real payload
        // lives server-side (see below), keyed by this handle.
        $token = bin2hex(random_bytes(16));

        $item = $this->cache->getItem(self::CACHE_KEY_PREFIX . $token);
        $item->set(['resource' => $this->resource($categoryId, $userId), 'grants' => $grantsJson]);
        $item->expiresAfter(self::TOKEN_TTL_SECONDS);

        $this->cache->save($item);

        return new CategoryExportTokenResponseDTO(
            token: $token,
            expiresAt: new DateTimeImmutable('+' . self::TOKEN_TTL_SECONDS . ' seconds')->format(DateTimeInterface::ATOM),
        );
    }

    #[Override]
    public function apply(Request $request, int $categoryId, int $userId): void
    {
        $token = $request->query->get('token');
        if (null === $token || '' === $token) {
            return;
        }

        $cacheKey = self::CACHE_KEY_PREFIX . $token;
        $item = $this->cache->getItem($cacheKey);
        $isHit = $item->isHit();
        $payload = $item->get();

        // Single-use, whether or not it turns out to be valid — a token
        // that's already been consulted once can never be replayed.
        $this->cache->deleteItem($cacheKey);

        if (!$isHit || !is_array($payload) || !isset($payload['resource'], $payload['grants']) || !is_string($payload['resource']) || !is_string($payload['grants'])) {
            throw new ForbiddenException(message: 'category.export_token_invalid');
        }

        if ($this->resource($categoryId, $userId) !== $payload['resource']) {
            throw new ForbiddenException(message: 'category.export_token_invalid');
        }

        $request->headers->set(AccessKeyGuardInterface::GRANTS_HEADER, $payload['grants']);
    }

    private function resource(int $categoryId, int $userId): string
    {
        return 'category-export:' . $categoryId . ':u' . $userId;
    }
}
