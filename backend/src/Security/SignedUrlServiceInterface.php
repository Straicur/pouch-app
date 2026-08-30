<?php

declare(strict_types = 1);

namespace App\Security;

interface SignedUrlServiceInterface
{
    /**
     * @return array{expires: int, signature: string}
     */
    public function sign(string $resource, int $ttlSeconds): array;

    public function isValid(string $resource, int $expires, string $signature): bool;
}
