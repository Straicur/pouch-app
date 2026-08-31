<?php

declare(strict_types = 1);

namespace App\Services\Item\Resolver;

use function array_pop;
use function end;
use function explode;
use function implode;
use function is_int;
use function is_string;
use function parse_url;
use function str_ends_with;
use function str_starts_with;
use function strrpos;
use function substr;

use const PHP_URL_FRAGMENT;
use const PHP_URL_HOST;
use const PHP_URL_PASS;
use const PHP_URL_PATH;
use const PHP_URL_PORT;
use const PHP_URL_QUERY;
use const PHP_URL_SCHEME;
use const PHP_URL_USER;

/**
 * Post-review fix: OpenGraphScraper used to resolve a relative og:image (or
 * redirect Location) by always docking it onto the page's *origin* — for
 * `https://example.com/articles/123/page.html` with `og:image="cover.jpg"`,
 * that produced `https://example.com/cover.jpg` instead of the correct
 * `https://example.com/articles/123/cover.jpg`. A standalone, static class
 * (no state, no I/O) implementing RFC 3986 §5.3's reference-resolution
 * algorithm — trimmed to the parts that matter for the two callers here
 * (OG image URLs, redirect Location headers): no relative-reference userinfo
 * handling beyond carrying the base's along, dot-segment removal kept simple
 * rather than the RFC's full state machine.
 */
final class UrlResolver
{
    public static function resolve(string $baseUrl, string $reference): string
    {
        if ('' === $reference) {
            return $baseUrl;
        }

        $refScheme = parse_url($reference, PHP_URL_SCHEME);
        if (is_string($refScheme)) {
            // Already absolute — used as-is (a relative reference can't have
            // its own scheme).
            return $reference;
        }

        $base = self::parts($baseUrl);
        $scheme = $base['scheme'] ?? 'http';

        // Network-path reference ("//host/path") — keeps the base's scheme,
        // everything else comes from the reference.
        if (str_starts_with($reference, '//')) {
            return $scheme . ':' . $reference;
        }

        $refHost = parse_url($reference, PHP_URL_HOST);
        if (is_string($refHost)) {
            // Malformed edge case (a host with no leading "//") — treat as
            // absolute rather than guessing.
            return $reference;
        }

        // Post-review fix: parse_url() can return `false` (malformed input),
        // not just `null` (component absent) — `?? ''` only ever catches the
        // latter, so a malformed $reference used to hand `false` straight to
        // str_starts_with()/mergePaths() below and blow up with a TypeError.
        // Since $reference is attacker-controlled (og:image, redirect
        // Location), treating "couldn't parse" the same as "no path" here —
        // not throwing — keeps a malformed value from taking down the whole
        // scrape/redirect-follow instead of just this one field resolving
        // to nothing useful.
        $refPathRaw = parse_url($reference, PHP_URL_PATH);
        $refPath = is_string($refPathRaw) ? $refPathRaw : '';

        $refQueryRaw = parse_url($reference, PHP_URL_QUERY);
        $refQuery = is_string($refQueryRaw) ? $refQueryRaw : null;

        $refFragmentRaw = parse_url($reference, PHP_URL_FRAGMENT);
        $refFragment = is_string($refFragmentRaw) ? $refFragmentRaw : null;

        $result = $base;
        unset($result['fragment']);

        if ('' !== $refPath) {
            $result['path'] = str_starts_with($refPath, '/') ? $refPath : self::mergePaths($base, $refPath);
            $result['path'] = self::removeDotSegments($result['path']);
            $result['query'] = $refQuery;
        } elseif (is_string($refQuery)) {
            $result['query'] = $refQuery;
        }

        if (is_string($refFragment)) {
            $result['fragment'] = $refFragment;
        }

        return self::build($scheme, $result);
    }

    /**
     * @return array{scheme?: string, user?: string, pass?: string, host?: string, port?: int, path?: string, query?: string, fragment?: string}
     */
    private static function parts(string $url): array
    {
        $parts = [];

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (is_string($scheme)) {
            $parts['scheme'] = $scheme;
        }

        $user = parse_url($url, PHP_URL_USER);
        if (is_string($user)) {
            $parts['user'] = $user;
        }

        $pass = parse_url($url, PHP_URL_PASS);
        if (is_string($pass)) {
            $parts['pass'] = $pass;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host)) {
            $parts['host'] = $host;
        }

        $port = parse_url($url, PHP_URL_PORT);
        if (is_int($port)) {
            $parts['port'] = $port;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path)) {
            $parts['path'] = $path;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query)) {
            $parts['query'] = $query;
        }

        return $parts;
    }

    /**
     * @param array{path?: string, host?: string} $base
     */
    private static function mergePaths(array $base, string $refPath): string
    {
        if (isset($base['host']) && (!isset($base['path']) || '' === $base['path'])) {
            return '/' . $refPath;
        }

        $basePath = $base['path'] ?? '';
        $lastSlash = strrpos($basePath, '/');

        return (false !== $lastSlash ? substr($basePath, 0, $lastSlash + 1) : '') . $refPath;
    }

    /**
     * Simplified RFC 3986 §5.2.4 — resolves "." and ".." segments. Not the
     * full input/output-buffer state machine, but equivalent for any path a
     * real redirect/og:image reference would plausibly contain.
     */
    private static function removeDotSegments(string $path): string
    {
        $leadingSlash = str_starts_with($path, '/');
        $trailingSlash = '' !== $path && str_ends_with($path, '/');
        $segments = explode('/', $path);
        $output = [];

        foreach ($segments as $segment) {
            if ('' === $segment) {
                continue;
            }

            if ('.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                if ([] !== $output && '..' !== end($output)) {
                    array_pop($output);
                }

                continue;
            }

            $output[] = $segment;
        }

        $resolved = ($leadingSlash ? '/' : '') . implode('/', $output);

        if ($trailingSlash && !str_ends_with($resolved, '/')) {
            $resolved .= '/';
        }

        return '' === $resolved ? '/' : $resolved;
    }

    /**
     * @param array{scheme?: string, user?: string, pass?: string, host?: string, port?: int, path?: string, query?: string|null, fragment?: string} $parts
     */
    private static function build(string $scheme, array $parts): string
    {
        $url = $scheme . '://';

        if (isset($parts['user'])) {
            $url .= $parts['user'];
            if (isset($parts['pass'])) {
                $url .= ':' . $parts['pass'];
            }

            $url .= '@';
        }

        $url .= $parts['host'] ?? '';

        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        $url .= $parts['path'] ?? '';

        if (isset($parts['query']) && '' !== $parts['query']) {
            $url .= '?' . $parts['query'];
        }

        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }
}
