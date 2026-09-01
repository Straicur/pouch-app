<?php

declare(strict_types = 1);

namespace App\Tests\Services\Item\Validator;

use App\ExceptionManagement\Exceptions\ApiException\BadRequestException\BadRequestException;
use App\Services\Item\Validator\UrlValidator;
use PHPUnit\Framework\TestCase;

/**
 * Post-review fix: UrlValidator used to check format/length/scheme only —
 * these cover the new DNS/IP-range check. Cases use literal IPs (no real DNS
 * lookup involved) except the "actually valid" ones, which need real
 * outbound DNS the same way every other URL-item test in this suite already
 * does (see ItemCreateUrlControllerTest's own docblock) — ScrapeUrlMessageHandler
 * runs inline against the real http_client in test env (messenger.yaml's
 * `async: sync://`), so those tests already assume it.
 */
final class UrlValidatorTest extends TestCase
{
    private function validator(): UrlValidator
    {
        return new UrlValidator();
    }

    public function testAcceptsAnOrdinaryPublicUrl(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator()->assertValid('https://example.com/article');
    }

    public function testRejectsTooLongAUrl(): void
    {
        $this->expectException(BadRequestException::class);

        $this->validator()->assertValid('https://example.com/' . str_repeat('a', 2048));
    }

    public function testRejectsAMalformedUrl(): void
    {
        $this->expectException(BadRequestException::class);

        $this->validator()->assertValid('not-a-url');
    }

    public function testRejectsADisallowedScheme(): void
    {
        $this->expectException(BadRequestException::class);

        $this->validator()->assertValid('file:///etc/passwd');
    }

    public function testRejectsALoopbackIpLiteral(): void
    {
        $this->expectException(BadRequestException::class);

        $this->validator()->assertValid('http://127.0.0.1/');
    }

    public function testRejectsAPrivateRfc1918IpLiteral(): void
    {
        $this->expectException(BadRequestException::class);

        $this->validator()->assertValid('http://192.168.1.1/');
    }

    /**
     * 169.254.169.254 — the AWS/GCP/Azure cloud instance-metadata address,
     * the canonical SSRF target this whole fix exists for.
     */
    public function testRejectsTheCloudMetadataAddress(): void
    {
        $this->expectException(BadRequestException::class);

        $this->validator()->assertValid('http://169.254.169.254/latest/meta-data/');
    }

    public function testRejectsAnIpv6LoopbackLiteral(): void
    {
        $this->expectException(BadRequestException::class);

        $this->validator()->assertValid('http://[::1]/');
    }

    /**
     * Część 18 point 4 — an IPv4-mapped IPv6 literal is a well-known way to
     * smuggle a private address past a check that only accounts for the
     * plain-IPv4 form; PHP's FILTER_FLAG_NO_PRIV_RANGE/NO_RES_RANGE combo
     * does recognize it correctly, but that was never pinned down by a test.
     */
    public function testRejectsAnIpv4MappedIpv6LoopbackLiteral(): void
    {
        $this->expectException(BadRequestException::class);

        $this->validator()->assertValid('http://[::ffff:127.0.0.1]/');
    }

    public function testRejectsAnIpv4MappedIpv6CloudMetadataLiteral(): void
    {
        $this->expectException(BadRequestException::class);

        $this->validator()->assertValid('http://[::ffff:169.254.169.254]/');
    }

    /**
     * Decimal-notation IPs (127.0.0.1 written as the single integer
     * 2130706433) aren't a bypass here — filter_var() doesn't recognize the
     * form as an IP at all, so it falls through to DNS resolution, which
     * fails for a numeric non-hostname and is rejected the same way any
     * other unresolvable host is.
     */
    public function testRejectsADecimalNotationIpAsAnUnresolvableHost(): void
    {
        $this->expectException(BadRequestException::class);

        $this->validator()->assertValid('http://2130706433/');
    }
}
