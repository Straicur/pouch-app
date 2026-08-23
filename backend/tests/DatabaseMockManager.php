<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\ConfigService;
use App\Security\CookieService;
use App\Security\TokenService;
use App\Tests\DTO\UserTestDTO;
use App\Util\PasswordHasher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;

class DatabaseMockManager
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    protected function getService(string $serviceName): object
    {
        return $this->container->get($serviceName);
    }

    public function createUser(UserTestDTO $userTestDTO): User
    {
        /**
         * @var UserRepository $userRepository
         */
        $userRepository = $this->getService(UserRepository::class);

        $user = new User(
            $userTestDTO->getEmail(),
            PasswordHasher::hash($userTestDTO->getPassword()),
        );

        $userRepository->saveUser($user);

        return $user;
    }

    public function loginUser(User $user): Cookie
    {
        /**
         * @var TokenService $tokenService
         */
        $tokenService = $this->getService(TokenService::class);
        /**
         * @var ConfigService $configService
         */
        $configService = $this->getService(ConfigService::class);
        /**
         * @var CookieService $cookieService
         */
        $cookieService = $this->getService(CookieService::class);

        $token = $tokenService->createToken($user);

        return $cookieService->prepareAuthCookie(
            name: CookieService::ACCESS_TOKEN,
            token: $token,
            expire: $configService->getAccessTokenTimeToLive(),
        );
    }
}
