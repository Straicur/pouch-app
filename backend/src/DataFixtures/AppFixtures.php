<?php

declare(strict_types = 1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture implements FixtureGroupInterface
{
    // Flat, no nesting — categories aren't per-user (Category has no owner
    // field), just enough real folders to exercise lists/filters manually.
    private const array CATEGORY_NAMES = ['Dokumenty', 'Zdjęcia', 'Linki', 'Notatki', 'Praca', 'Prywatne'];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Override]
    public function load(ObjectManager $manager): void
    {
        // Existing dev account — kept as a plain ROLE_USER (its default).
        $user = $this->createUser('mosinskidamian11@gmail.com', 'zaq1@WSX');
        $this->userRepository->saveUser(user: $user);

        // One account per role (Part 2 of the roadmap), for manual ACL checks
        // against Swagger (/api/doc) — same password for all three, for convenience.
        $admin = $this->createUser('admin@pouch.test', 'zaq1@WSX');
        $admin->setRoles(['ROLE_ADMIN']);

        $this->userRepository->saveUser(user: $admin);

        $guest = $this->createUser('guest@pouch.test', 'zaq1@WSX');
        $guest->setRoles(['ROLE_GUEST']);

        $this->userRepository->saveUser(user: $guest);

        foreach (self::CATEGORY_NAMES as $categoryName) {
            $this->categoryRepository->save(new Category($categoryName));
        }

        $manager->flush();
    }

    #[Override]
    public static function getGroups(): array
    {
        return ['default'];
    }

    // Post-review fix: used App\Util\PasswordHasher (a thin password_hash()
    // wrapper) — this goes through the real password_hashers config
    // (security.yaml) via Symfony's own hasher instead, same as every other
    // User password in the app now does (see AuthService).
    private function createUser(string $email, #[SensitiveParameter] string $plainPassword): User
    {
        $user = new User($email, '');
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        return $user;
    }
}
