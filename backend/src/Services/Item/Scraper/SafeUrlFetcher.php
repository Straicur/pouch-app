<?php

declare(strict_types = 1);

namespace App\Services\Item\Scraper;

use App\Services\Item\Resolver\UrlResolver;
use App\Services\Item\Validator\UrlValidator;
use Override;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

use function sprintf;

final readonly class SafeUrlFetcher implements SafeUrlFetcherInterface
{
    private const int MAX_REDIRECTS = 5;

    public function __construct(
        private HttpClientInterface $httpClient,
        private UrlValidator $urlValidator = new UrlValidator(),
    ) {}

    #[Override]
    public function fetch(string $url, array $options = []): ResponseInterface
    {
        $current = $url;

        for ($hop = 0; self::MAX_REDIRECTS >= $hop; ++$hop) {
            $pin = $this->urlValidator->assertValidAndPin($current);

            $response = $this->httpClient->request('GET', $current, [
                ...$options,
                'max_redirects' => 0,
                // Connects to the exact IP just validated instead of letting
                // the transport re-resolve the hostname itself — closes the
                // TOCTOU/DNS-rebinding gap between "we checked" and "we
                // connected" (see UrlValidator::assertValidAndPin()).
                //
                // Post-review fix: keyed by host only, not "host:port" — the
                // port is derived by the transport itself from the request
                // URL (see CurlHttpClient's own comment: "curl's resolve
                // feature varies by host:port but ours varies by host
                // only"). A "host:port" key here got mangled into
                // "host:port:port:ip" by cURL and silently mis-resolved (or
                // fell through to a real DNS lookup) with the Native
                // transport, defeating the whole point of pinning.
                'resolve' => [$pin['host'] => $pin['ip']],
            ]);

            $status = $response->getStatusCode();
            if (300 > $status || 400 <= $status) {
                return $response;
            }

            $location = $response->getHeaders(false)['location'][0] ?? null;
            if (null === $location) {
                return $response;
            }

            $current = UrlResolver::resolve($current, $location);
        }

        throw new RuntimeException(sprintf('Too many redirects fetching "%s"', $url));
    }

    #[Override]
    public function stream(ResponseInterface $response, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->httpClient->stream($response, $timeout);
    }
}
