<?php

declare(strict_types = 1);

namespace App\Services\Item\Scraper;

use App\Services\Item\Resolver\UrlResolver;
use DOMElement;
use Override;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

use function preg_replace;
use function sprintf;
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

    public function __construct(
        private SafeUrlFetcherInterface $safeUrlFetcher,
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
            $response = $this->safeUrlFetcher->fetch($url, [
                'timeout' => self::TIMEOUT_SECONDS,
                'headers' => ['User-Agent' => 'PouchBot/1.0 (+personal document archive; not a public crawler)'],
            ]);

            $html = '';
            foreach ($this->safeUrlFetcher->stream($response, self::TIMEOUT_SECONDS) as $chunk) {
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

        return UrlResolver::resolve($baseUrl, $maybeRelativeUrl);
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
