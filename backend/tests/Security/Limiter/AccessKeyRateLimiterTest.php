<?php

declare(strict_types = 1);

namespace App\Tests\Security\Limiter;

use App\ExceptionManagement\Exceptions\ApiException\TooManyRequestsException\TooManyRequestsException;
use App\Security\Limiter\AccessKeyRateLimiter;
use App\Security\Limiter\RateLimiterGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Built directly on a RateLimiterFactory with a tiny limit, not through the
 * kernel — the app's own "access_key" limiter is bumped to 1000/15min in
 * when@test (see config/packages/rate_limiter.yaml), same as "login", so it
 * can't realistically be exercised through a functional/webClient test.
 */
final class AccessKeyRateLimiterTest extends TestCase
{
    private function makeLimiter(int $limit): AccessKeyRateLimiter
    {
        // No LockFactory (symfony/lock isn't a project dependency) — RateLimiterFactory
        // treats that as "no locking", which is fine for a single-process test.
        $factory = new RateLimiterFactory(
            ['id' => 'access_key_test', 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '15 minutes'],
            new InMemoryStorage(),
        );

        return new AccessKeyRateLimiter($factory, new RateLimiterGuard());
    }

    public function testConsumeSucceedsUpToTheLimit(): void
    {
        $limiter = $this->makeLimiter(2);
        $request = Request::create('/api/categories/1/unlock', 'POST', server: ['REMOTE_ADDR' => '203.0.113.10']);

        $limiter->consume($request);
        $limiter->consume($request);

        $this->addToAssertionCountIfNoExceptionThrown();
    }

    public function testConsumeThrowsTooManyRequestsPastTheLimit(): void
    {
        $limiter = $this->makeLimiter(2);
        $request = Request::create('/api/categories/1/unlock', 'POST', server: ['REMOTE_ADDR' => '203.0.113.11']);

        $limiter->consume($request);
        $limiter->consume($request);

        $this->expectException(TooManyRequestsException::class);
        $limiter->consume($request);
    }

    public function testDifferentIpsAreRateLimitedIndependently(): void
    {
        $limiter = $this->makeLimiter(1);

        $limiter->consume(Request::create('/api/categories/1/unlock', 'POST', server: ['REMOTE_ADDR' => '203.0.113.20']));
        $limiter->consume(Request::create('/api/categories/1/unlock', 'POST', server: ['REMOTE_ADDR' => '203.0.113.21']));

        $this->addToAssertionCountIfNoExceptionThrown();
    }

    private function addToAssertionCountIfNoExceptionThrown(): void
    {
        self::assertTrue(true);
    }
}
