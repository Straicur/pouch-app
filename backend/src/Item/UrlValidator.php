<?php

declare(strict_types = 1);

namespace App\Item;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;

use function filter_var;
use function in_array;
use function parse_url;
use function sprintf;
use function strlen;

use const FILTER_VALIDATE_URL;
use const PHP_URL_SCHEME;

/**
 * Deliberately minimal — this is a self-hosted, single/few-user tool where the
 * only person who can add a URL item is already an authenticated user, so
 * full SSRF hardening (resolving DNS, blocking private/internal IP ranges)
 * is left out of MVP scope rather than half-done.
 */
final class UrlValidator
{
    private const int MAX_LENGTH = 2048;

    /**
     * @var list<string>
     */
    private const array ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * @throws BadRequestException
     */
    public function assertValid(string $url): void
    {
        if (strlen($url) > self::MAX_LENGTH) {
            throw new BadRequestException(message: sprintf('URL exceeds the maximum length of %d characters', self::MAX_LENGTH));
        }

        if (false === filter_var($url, FILTER_VALIDATE_URL)) {
            throw new BadRequestException(message: 'Not a valid URL');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (false === in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new BadRequestException(message: 'Only http:// and https:// URLs are allowed');
        }
    }
}
