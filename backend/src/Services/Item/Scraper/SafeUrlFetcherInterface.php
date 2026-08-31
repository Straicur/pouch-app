<?php

declare(strict_types = 1);

namespace App\Services\Item\Scraper;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use RuntimeException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * The one place any outbound fetch of a user/page-supplied URL goes through
 * — OpenGraphScraper (the page itself) and ScrapeUrlMessageHandler (the OG
 * image) both used to make their own httpClient->request() calls directly,
 * which is how the OG-image download ended up with none of the SSRF
 * protection the page fetch got (post-review fix).
 */
interface SafeUrlFetcherInterface
{
    /**
     * GET $url, validating the host (DNS-resolved, must be publicly
     * routable, and pinned by IP against rebinding — see UrlValidator)
     * before every request this makes, including each redirect hop:
     * Symfony's HttpClient normally follows redirects internally without
     * giving the caller any chance to check where a malicious/compromised
     * server is sending it next — exactly the gap an SSRF-via-redirect
     * exploits.
     *
     * @param array<string, mixed> $options merged into every hop's request options — `max_redirects` and `resolve` are always overridden
     *
     * @throws BadRequestException if $url or any redirect target isn't safe to fetch
     * @throws RuntimeException    on too many redirects
     */
    public function fetch(string $url, array $options = []): ResponseInterface;

    /**
     * Passthrough to the underlying HttpClient's stream() — a caller needs
     * this to actually read the body of whatever fetch() returned.
     */
    public function stream(ResponseInterface $response, ?float $timeout = null): ResponseStreamInterface;
}
