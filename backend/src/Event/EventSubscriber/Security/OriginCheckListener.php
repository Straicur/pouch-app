<?php

declare(strict_types = 1);

namespace App\Event\EventSubscriber\Security;

use App\ExceptionManagement\Exceptions\ApiException\ForbiddenException\ForbiddenException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function in_array;
use function is_int;
use function is_string;
use function parse_url;
use function preg_match;

use const PHP_URL_HOST;
use const PHP_URL_PORT;
use const PHP_URL_SCHEME;

/**
 * Auth is entirely cookie-based (CookieService) — without this, a
 * third-party page could submit a state-changing request (e.g. a bodyless
 * POST like AdminController::runGc()) that the browser would happily attach
 * the logged-in user's cookies to. Second, independent layer on top of
 * CookieService's SameSite=Lax: reject any state-changing request whose
 * Origin (or, failing that, Referer) doesn't match this app's own frontend,
 * using the exact same allow-list nelmio/cors-bundle already enforces for
 * cross-origin fetch/XHR (CORS_ALLOW_ORIGIN) — one source of truth for "what
 * counts as us".
 *
 * Disabled in test env (see .env.test) — none of the existing functional
 * tests send an Origin/Referer header, the same reason the rate limiters are
 * bumped to 1000/15min there. OriginCheckListenerTest exercises this
 * directly instead, bypassing that toggle.
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest')]
final readonly class OriginCheckListener
{
    /**
     * @var list<string>
     */
    private const array PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        #[Autowire('%env(bool:CSRF_ORIGIN_CHECK_ENABLED)%')]
        private bool $enabled,
        #[Autowire('%env(CORS_ALLOW_ORIGIN)%')]
        private string $allowedOriginPattern,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (false === $this->enabled || false === $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (false === in_array($request->getMethod(), self::PROTECTED_METHODS, true)) {
            return;
        }

        $origin = $request->headers->get('Origin') ?? $this->originFromReferer($request);

        // Anchored here regardless of CORS_ALLOW_ORIGIN's own ^/$ — an unanchored
        // pattern would let preg_match match a substring (e.g. an attacker's
        // "our-app.com.evil.com" origin) instead of the full origin.
        if (null === $origin || 1 !== preg_match('#^(?:' . $this->allowedOriginPattern . ')$#', $origin)) {
            throw new ForbiddenException(message: 'csrf.origin_not_allowed');
        }
    }

    private function originFromReferer(Request $request): ?string
    {
        $referer = $request->headers->get('Referer');
        if (null === $referer) {
            return null;
        }

        $scheme = parse_url($referer, PHP_URL_SCHEME);
        $host = parse_url($referer, PHP_URL_HOST);
        if (false === is_string($scheme) || false === is_string($host)) {
            return null;
        }

        $port = parse_url($referer, PHP_URL_PORT);

        return $scheme . '://' . $host . (is_int($port) ? ':' . $port : '');
    }
}
