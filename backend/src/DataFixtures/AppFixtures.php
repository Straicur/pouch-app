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
        $user = new User(
            'mosinskidamian11@gmail.com',
            PasswordHasher::hash('zaq1@WSX')
        );
        $this->userRepository->saveUser(user: $user);
        $manager->flush();
    }

    #[Override]
    public static function getGroups(): array
    {
        return ['default'];
    }
}
