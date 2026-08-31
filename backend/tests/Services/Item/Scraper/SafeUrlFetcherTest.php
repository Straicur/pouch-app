<?php

declare(strict_types = 1);

namespace App\Tests\Services\Item\Scraper;

use App\Services\Item\Scraper\SafeUrlFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Post-review fix: the `resolve` option handed to the underlying
 * HttpClientInterface used to be keyed "host:port" — Symfony's own transports
 * derive the port from the request URL themselves (CurlHttpClient's own
 * comment: "curl's resolve feature varies by host:port but ours varies by
 * host only"), so a "host:port" key either got silently mangled (cURL,
 * producing a bogus "host:port:port:ip" CURLOPT_RESOLVE entry) or just never
 * matched (Native), defeating the IP pin entirely and leaving the DNS-
 * rebinding gap it exists to close wide open.
 */
final class SafeUrlFetcherTest extends TestCase
{
    public function testResolveOptionIsKeyedByHostOnlyNotHostAndPort(): void
    {
        $capturedOptions = null;

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions) {
            $capturedOptions = $options;

            return new MockResponse('ok', ['response_headers' => ['content-type' => 'text/plain']]);
        });

        (new SafeUrlFetcher($client))->fetch('https://example.com/article');

        self::assertIsArray($capturedOptions);
        self::assertArrayHasKey('resolve', $capturedOptions);
        self::assertArrayHasKey('example.com', $capturedOptions['resolve']);
        self::assertArrayNotHasKey('example.com:443', $capturedOptions['resolve']);
    }
}
