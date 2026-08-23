<?php

declare(strict_types = 1);

namespace App\Event\EventSubscriber;

use App\Security\ConfigServiceInterface;
use App\Security\CookieService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS, method: 'attachRefreshToken', priority: -10)]
final readonly class RefreshTokenSuccessListener
{
    public function __construct(
        private ConfigServiceInterface $configService,
        private RequestStack $requestStack,
        private CookieService $cookieService,
    ) {}

    public function attachRefreshToken(AuthenticationSuccessEvent $event): void
    {
        /**
         * @var string|null $token
         */
        $token = $event->getData()['token'] ?? null;

        if (null === $token) {
            return;
        }

        $event->setData([]);

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || $request->cookies->has(CookieService::ACCESS_TOKEN)) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            $this->cookieService->prepareAuthCookie(
                name: CookieService::ACCESS_TOKEN,
                token: $token,
                expire: $this->configService->getAccessTokenTimeToLive(),
            )
        );
    }
}
