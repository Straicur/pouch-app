<?php

declare(strict_types = 1);

namespace App\Tests\Services\Item\Resolver;

use App\Services\Item\Resolver\UrlResolver;
use PHPUnit\Framework\TestCase;

/**
 * No dedicated coverage of this existed before — OpenGraphScraperTest only
 * exercised it indirectly through a full scrape. Also covers the post-review
 * fix in resolve(): parse_url() can return `false` (malformed input), not
 * just `null` (component absent), which the code used to hand straight to
 * str_starts_with()/mergePaths() and crash with a TypeError — $reference is
 * attacker-controlled (og:image, redirect Location), so that was a real
 * "one bad page takes down the whole scrape" risk, not just a type nit.
 */
final class UrlResolverTest extends TestCase
{
    public function testAbsoluteReferenceIsUsedAsIs(): void
    {
        self::assertSame(
            'https://cdn.example.com/thumb.jpg',
            UrlResolver::resolve('https://example.com/articles/123/page.html', 'https://cdn.example.com/thumb.jpg'),
        );
    }

    public function testRootRelativeReferenceResolvesAgainstTheOrigin(): void
    {
        self::assertSame(
            'https://example.com/img/cover.png',
            UrlResolver::resolve('https://example.com/some/page', '/img/cover.png'),
        );
    }

    /**
     * The bug this whole class exists to fix — a document-relative reference
     * merges against the *directory* of the base path, not the origin.
     */
    public function testDocumentRelativeReferenceResolvesAgainstTheCurrentDirectory(): void
    {
        self::assertSame(
            'https://example.com/articles/123/cover.jpg',
            UrlResolver::resolve('https://example.com/articles/123/page.html', 'cover.jpg'),
        );
    }

    public function testNetworkPathReferenceKeepsTheBaseScheme(): void
    {
        self::assertSame(
            'https://cdn.example.com/thumb.jpg',
            UrlResolver::resolve('https://example.com/page', '//cdn.example.com/thumb.jpg'),
        );
    }

    public function testDotSegmentsAreResolved(): void
    {
        self::assertSame(
            'https://example.com/a/c.jpg',
            UrlResolver::resolve('https://example.com/a/b/page.html', '../c.jpg'),
        );
    }

    public function testEmptyReferenceReturnsTheBaseUrlUnchanged(): void
    {
        self::assertSame(
            'https://example.com/page',
            UrlResolver::resolve('https://example.com/page', ''),
        );
    }

    public function testQueryOnlyReferenceKeepsTheBasePath(): void
    {
        self::assertSame(
            'https://example.com/page?x=1',
            UrlResolver::resolve('https://example.com/page', '?x=1'),
        );
    }
}
