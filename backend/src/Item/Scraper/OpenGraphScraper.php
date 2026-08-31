<?php

declare(strict_types = 1);

namespace App\Item\Scraper;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\Item\UrlValidator;
use DOMElement;
use Override;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

use function parse_url;
use function preg_replace;
use function sprintf;
use function str_starts_with;
use function strlen;
use function trim;

/**
 * The roadmap's "one handler as a pattern" (ScrapeUrlMessageHandler) leans on
 * this to do the actual HTTP + parsing work, kept separate so it's unit
 * testable with a MockHttpClient and reusable if a second URL-consuming
 * feature ever needs it.
 */
final readonly class OpenGraphScraper implements OpenGraphScraperInterface
{
    // Bounds how much of a page we read — some pages are enormous and we only need the <head>/visible text.
    private const int MAX_CONTENT_LENGTH = 5 * 1024 * 1024;

    private const int TIMEOUT_SECONDS = 10;

    private const int MAX_REDIRECTS = 5;

    public function __construct(
        private HttpClientInterface $httpClient,
        private UrlValidator $urlValidator = new UrlValidator(),
    ) {}

    #[Override]
    public function scrape(string $url): ScrapedPage
    {
        $html = $this->fetch($url);
        $crawler = new Crawler($html, $url);

        $title = $this->metaContent($crawler, 'og:title') ?? $this->titleTag($crawler);
        $description = $this->metaContent($crawler, 'og:description') ?? $this->metaContent($crawler, 'description');
        $imageUrl = $this->resolveUrl($this->metaContent($crawler, 'og:image'), $url);
        $text = $this->visibleText($crawler);

        return new ScrapedPage(title: $title, description: $description, imageUrl: $imageUrl, text: $text);
    }

    /**
     * @throws RuntimeException
     */
    private function fetch(string $url): string
    {
        try {
            $response = $this->requestFollowingSafeRedirects($url);

            $html = '';
            foreach ($this->httpClient->stream($response, self::TIMEOUT_SECONDS) as $chunk) {
                $html .= $chunk->getContent();
                if (strlen($html) > self::MAX_CONTENT_LENGTH) {
                    break;
                }
            }
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf('Could not fetch "%s": %s', $url, $exception->getMessage()), (int) $exception->getCode(), previous: $exception);
        }

        return $html;
    }

    /**
     * Post-review fix: the old code just passed `max_redirects: 5` and let
     * Symfony's HttpClient follow them on its own, with no chance to see
     * where it actually went — a compromised (or malicious from the start)
     * page can 302 straight at 169.254.169.254 or an RFC1918 address, and
     * the SSRF check on the *input* URL (UrlValidator, called at item-
     * creation time) never sees that hop at all. Redirects are disabled
     * here (`max_redirects: 0`) and followed by hand instead, re-running
     * that same host check before every one of them, input URL included.
     *
     * @throws BadRequestException if $url or any redirect target isn't safe to fetch
     * @throws RuntimeException    on too many redirects
     */
    private function requestFollowingSafeRedirects(string $url): ResponseInterface
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; ++$hop) {
            $this->urlValidator->assertValid($current);

            $response = $this->httpClient->request('GET', $current, [
                'timeout'       => self::TIMEOUT_SECONDS,
                'max_redirects' => 0,
                'headers'       => ['User-Agent' => 'PouchBot/1.0 (+personal document archive; not a public crawler)'],
            ]);

            $status = $response->getStatusCode();
            if ($status < 300 || $status >= 400) {
                return $response;
            }

            $location = $response->getHeaders(false)['location'][0] ?? null;
            if (null === $location) {
                return $response;
            }

            $current = $this->resolveUrl($location, $current) ?? $location;
        }

        throw new RuntimeException(sprintf('Too many redirects fetching "%s"', $url));
    }

    private function metaContent(Crawler $crawler, string $property): ?string
    {
        $nodes = $crawler->filter(sprintf('meta[property="%s"], meta[name="%s"]', $property, $property));
        if (0 === $nodes->count()) {
            return null;
        }

        $content = $nodes->first()->attr('content');

        return null !== $content && '' !== trim($content) ? trim($content) : null;
    }

    private function titleTag(Crawler $crawler): ?string
    {
        $nodes = $crawler->filter('title');
        if (0 === $nodes->count()) {
            return null;
        }

        $text = trim($nodes->first()->text(''));

        return '' !== $text ? $text : null;
    }

    private function resolveUrl(?string $maybeRelativeUrl, string $baseUrl): ?string
    {
        if (null === $maybeRelativeUrl || '' === $maybeRelativeUrl) {
            return null;
        }

        if (str_starts_with($maybeRelativeUrl, 'http://') || str_starts_with($maybeRelativeUrl, 'https://')) {
            return $maybeRelativeUrl;
        }

        $base = parse_url($baseUrl);
        if (false === $base || false === isset($base['scheme'], $base['host'])) {
            return null;
        }

        $origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');

        return str_starts_with($maybeRelativeUrl, '/')
            ? $origin . $maybeRelativeUrl
            : $origin . '/' . $maybeRelativeUrl;
    }

    /**
     * Product doc: "snapshot treści strony" — visible text only, so
     * script/style contents don't pollute the future search index.
     */
    private function visibleText(Crawler $crawler): ?string
    {
        $bodyNodes = $crawler->filter('body');
        if (0 === $bodyNodes->count()) {
            return null;
        }

        $body = $bodyNodes->first();
        foreach ($body->filter('script, style, noscript')->getIterator() as $node) {
            if ($node instanceof DOMElement) {
                $node->parentNode?->removeChild($node);
            }
        }

        $text = trim((string) preg_replace('/\s+/', ' ', $body->text('')));

        return '' !== $text ? $text : null;
    }
}
