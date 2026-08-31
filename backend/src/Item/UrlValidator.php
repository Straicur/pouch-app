<?php

declare(strict_types = 1);

namespace App\Item;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function dns_get_record;
use function filter_var;
use function gethostbynamel;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function parse_url;
use function strlen;

use const DNS_AAAA;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;
use const FILTER_VALIDATE_URL;
use const PHP_URL_HOST;
use const PHP_URL_PORT;
use const PHP_URL_SCHEME;

/**
 * Post-review fix: this used to be deliberately minimal — format/length/
 * scheme only, no DNS resolution or IP-range check at all, on the reasoning
 * that a self-hosted, single/few-user tool only ever gets a URL from an
 * already-authenticated user. That's still true for the *input* URL — the
 * gap the roadmap flagged was that a scraped page can itself redirect
 * (Location header) or point at an OG image hosted anywhere, and neither of
 * those targets was ever checked. Resolving DNS and rejecting private/
 * loopback/link-local/reserved IPs now applies here, and SafeUrlFetcher
 * calls assertValidAndPin() again for every redirect hop (and the final OG
 * image fetch), not just the URL a user typed in when creating the item.
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
        $this->assertValidAndPin($url);
    }

    /**
     * Same checks as assertValid(), plus the IP actually picked as safe —
     * for a caller that's about to make the real request, handing this to
     * Symfony HttpClient's `resolve` option pins the connection to the
     * address just verified, so a second DNS lookup (the client's own, at
     * connect time) can't answer something different — the classic
     * TOCTOU/rebinding gap a check-then-fetch pair otherwise leaves open.
     *
     * @return array{host: string, port: int, ip: string}
     *
     * @throws BadRequestException
     */
    public function assertValidAndPin(string $url): array
    {
        if (strlen($url) > self::MAX_LENGTH) {
            throw new BadRequestException(message: 'url.too_long');
        }

        if (false === filter_var($url, FILTER_VALIDATE_URL)) {
            throw new BadRequestException(message: 'url.invalid');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (false === is_string($scheme) || false === in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new BadRequestException(message: 'url.scheme_not_allowed');
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (false === is_string($host) || '' === $host) {
            throw new BadRequestException(message: 'url.invalid');
        }

        $rawPort = parse_url($url, PHP_URL_PORT);
        $port = is_int($rawPort) ? $rawPort : match ($scheme) {
            'https' => 443,
            default => 80,
        };

        return ['host' => $host, 'port' => $port, 'ip' => $this->safeIpFor($host)];
    }

    /**
     * A host is safe to fetch only if *every* address it resolves to is a
     * public one — a hostname that round-robins between a public and an
     * internal IP is exactly the kind of thing a rebinding attack relies on.
     * Returns the first one, to pin the actual request to.
     *
     * @throws BadRequestException
     */
    private function safeIpFor(string $host): string
    {
        // A literal IP in the URL itself (http://127.0.0.1/...) — no DNS
        // involved, check it directly.
        if (false !== filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertIpIsPubliclyRoutable($host);

            return $host;
        }

        $addresses = $this->resolve($host);
        if ([] === $addresses) {
            throw new BadRequestException(message: 'url.host_unresolvable');
        }

        foreach ($addresses as $address) {
            $this->assertIpIsPubliclyRoutable($address);
        }

        return $addresses[0];
    }

    private function assertIpIsPubliclyRoutable(string $ip): void
    {
        // Loopback (127.0.0.0/8, ::1), RFC1918/ULA private ranges, and the
        // rest of the reserved/link-local space (169.254.0.0/16 — the cloud
        // metadata endpoint's range — included) are all excluded by
        // combining these two flags; this is the standard PHP idiom for it.
        $public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        if (false === $public) {
            throw new BadRequestException(message: 'url.host_not_allowed');
        }
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        // gethostbynamel() only ever does A (IPv4) lookups — AAAA needs
        // dns_get_record() separately. Both fail silently (false/empty
        // array) rather than throwing on an unresolvable/misbehaving host,
        // which is exactly what safeIpFor() above treats as "reject" already.
        $ipv4 = gethostbynamel($host);
        $ipv6Records = @dns_get_record($host, DNS_AAAA);

        $ipv6 = is_array($ipv6Records)
            ? array_values(array_filter(array_map(
                static fn (array $record): ?string => isset($record['ipv6']) && is_string($record['ipv6']) ? $record['ipv6'] : null,
                $ipv6Records,
            ), static fn (?string $address): bool => null !== $address))
            : [];

        return array_values(array_unique(array_merge(false !== $ipv4 ? $ipv4 : [], $ipv6)));
    }
}
