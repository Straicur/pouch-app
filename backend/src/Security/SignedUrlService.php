<?php

declare(strict_types = 1);

namespace App\Security;

use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function hash_equals;
use function hash_hmac;
use function time;

/**
 * HMAC + expiry-timestamp signed links (product doc: "niezależny od auth-tokena
 * użytkownika") — used today for item downloads (Part 3), and meant to be
 * reused as-is for the public item links in Part 9 (just a longer TTL).
 */
final readonly class SignedUrlService implements SignedUrlServiceInterface
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {}

    #[Override]
    public function sign(string $resource, int $ttlSeconds): array
    {
        $expires = time() + $ttlSeconds;

        return [
            'expires'   => $expires,
            'signature' => $this->computeSignature($resource, $expires),
        ];
    }

    #[Override]
    public function isValid(string $resource, int $expires, string $signature): bool
    {
        if (time() > $expires) {
            return false;
        }

        return hash_equals($this->computeSignature($resource, $expires), $signature);
    }

    private function computeSignature(string $resource, int $expires): string
    {
        return hash_hmac('sha256', $resource . '|' . $expires, $this->secret);
    }
}
