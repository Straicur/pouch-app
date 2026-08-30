<?php

declare(strict_types = 1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Util\PasswordHasher;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

class AppFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    #[Override]
    public function load(ObjectManager $manager): void
    {
        // Existing dev account — kept as a plain ROLE_USER (its default).
        $user = new User(
            'mosinskidamian11@gmail.com',
            PasswordHasher::hash('zaq1@WSX')
        );
        $this->userRepository->saveUser(user: $user);

        // One account per role (Part 2 of the roadmap), for manual ACL checks
        // against Swagger (/api/doc) — same password for all three, for convenience.
        $admin = new User('admin@pouch.test', PasswordHasher::hash('zaq1@WSX'));
        $admin->setRoles(['ROLE_ADMIN']);

        $this->userRepository->saveUser(user: $admin);

        $guest = new User('guest@pouch.test', PasswordHasher::hash('zaq1@WSX'));
        $guest->setRoles(['ROLE_GUEST']);

        $this->userRepository->saveUser(user: $guest);

        $manager->flush();
    }

    #[Override]
    public static function getGroups(): array
    {
        return ['default'];
    }
}
