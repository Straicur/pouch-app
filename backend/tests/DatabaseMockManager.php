<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Category;
use App\Entity\Pouch;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\PouchRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Security\ConfigService;
use App\Security\CookieService;
use App\Security\TokenService;
use App\Tests\DTO\UserTestDTO;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DatabaseMockManager
{
    private ContainerInterface $container;

    // Lazily created, reused for this manager's lifetime — createUser()/createCategory()
    // fall back to it when no Pouch is given explicitly.
    private ?Pouch $defaultPouch = null;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    protected function getService(string $serviceName): object
    {
        return $this->container->get($serviceName);
    }

    public function createPouch(string $name = 'Test pouch'): Pouch
    {
        /**
         * @var PouchRepository $pouchRepository
         */
        $pouchRepository = $this->getService(PouchRepository::class);

        $pouch = new Pouch($name);
        $pouchRepository->save($pouch);

        return $pouch;
    }

    private function defaultPouch(): Pouch
    {
        return $this->defaultPouch ??= $this->createPouch('Default test pouch');
    }

    public function createUser(UserTestDTO $userTestDTO, ?Pouch $pouch = null): User
    {
        /**
         * @var UserRepository $userRepository
         */
        $userRepository = $this->getService(UserRepository::class);
        /**
         * @var UserPasswordHasherInterface $passwordHasher
         */
        $passwordHasher = $this->getService(UserPasswordHasherInterface::class);

        // Post-review fix: used App\Util\PasswordHasher (hardcoded bcrypt,
        // cost 15, regardless of environment) — the real hasher factory
        // picks up when@test's much cheaper cost (security.yaml), so every
        // test user created across the whole suite got measurably faster to
        // create for free.
        $user = new User($userTestDTO->getEmail(), '', $pouch ?? $this->defaultPouch());
        $user->setPassword($passwordHasher->hashPassword($user, $userTestDTO->getPassword()));
        $user->setRoles($userTestDTO->getRoles());

        $userRepository->saveUser($user);

        return $user;
    }

    public function createCategory(string $name, ?Category $parent = null, ?Pouch $pouch = null): Category
    {
        /**
         * @var CategoryRepository $categoryRepository
         */
        $categoryRepository = $this->getService(CategoryRepository::class);

        $category = new Category($name, $pouch ?? $this->defaultPouch(), $parent);
        $categoryRepository->save($category);

        return $category;
    }

    public function createTag(string $name, ?Pouch $pouch = null): Tag
    {
        /**
         * @var TagRepository $tagRepository
         */
        $tagRepository = $this->getService(TagRepository::class);

        $tag = new Tag($name, $pouch ?? $this->defaultPouch());
        $tagRepository->save($tag);

        return $tag;
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

    public function createRefreshToken(User $user): string
    {
        /**
         * @var TokenService $tokenService
         */
        $tokenService = $this->getService(TokenService::class);

        return $tokenService->createRefreshToken($user)->getRefreshToken();
    }
}
