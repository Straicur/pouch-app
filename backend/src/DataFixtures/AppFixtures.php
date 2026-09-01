<?php

declare(strict_types = 1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Pouch;
use App\Entity\User;
use App\Enum\TtlPreset;
use App\Repository\CategoryRepository;
use App\Repository\PouchRepository;
use App\Repository\UserRepository;
use App\Services\Item\ItemServiceInterface;
use App\Services\Item\ValueObject\ItemLifecycleOptions;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use LogicException;
use Override;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

use function count;
use function sprintf;

class AppFixtures extends Fixture implements FixtureGroupInterface
{
    // Flat, no nesting — all three accounts share one pouch (see load()),
    // just enough real folders to exercise lists/filters manually.
    private const array CATEGORY_NAMES = ['Dokumenty', 'Zdjęcia', 'Linki', 'Notatki', 'Praca', 'Prywatne'];

    // Note items (ItemServiceInterface::createNote), not file/URL/photo —
    // those would need a real upload or an actual network fetch (Messenger
    // dispatch) to look right, which fixtures shouldn't depend on. Enough to
    // exercise GET /api/items' pagination (ItemController::DEFAULT_PAGE_SIZE
    // is 50) and its category/favorite/tag filters manually.
    private const int ITEM_COUNT = 50;

    private const array NOTE_SUBJECTS = [
        'Pomysł na refaktor', 'Lista zakupów', 'Notatka ze spotkania', 'Do sprawdzenia później',
        'Cytat, który mi się spodobał', 'Plan na weekend', 'Przepis', 'Link do obejrzenia',
        'Notatka techniczna', 'Losowa myśl',
    ];

    private const array TAG_POOLS = [
        ['praca'], ['prywatne'], ['ważne', 'praca'], ['pomysł'], [], ['przeczytać'], ['ważne'],
    ];

    public function __construct(
        private readonly PouchRepository $pouchRepository,
        private readonly UserRepository $userRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ItemServiceInterface $itemService,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    #[Override]
    public function load(ObjectManager $manager): void
    {
        // One pouch shared by all three accounts — admin/guest are only for manual ACL checks.
        $pouch = new Pouch('Domyślny pouch');
        $this->pouchRepository->save($pouch);

        // Existing dev account — kept as a plain ROLE_USER (its default).
        $user = $this->createUser('mosinskidamian11@gmail.com', 'zaq1@WSX', $pouch);
        $this->userRepository->saveUser(user: $user);

        // One account per role (Part 2 of the roadmap), for manual ACL checks
        // against Swagger (/api/doc) — same password for all three, for convenience.
        $admin = $this->createUser('admin@pouch.test', 'zaq1@WSX', $pouch);
        $admin->setRoles(['ROLE_ADMIN']);

        $this->userRepository->saveUser(user: $admin);

        $guest = $this->createUser('guest@pouch.test', 'zaq1@WSX', $pouch);
        $guest->setRoles(['ROLE_GUEST']);

        $this->userRepository->saveUser(user: $guest);

        $categories = [];
        foreach (self::CATEGORY_NAMES as $categoryName) {
            $category = new Category($categoryName, $pouch);
            $this->categoryRepository->save($category);
            $categories[] = $category;
        }

        // loadItems() needs an authenticated User (CategoryService resolves
        // the current pouch from it) — this CLI command has none, so fake one.
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $this->loadItems($categories);
    }

    /**
     * @param list<Category> $categories
     */
    private function loadItems(array $categories): void
    {
        $subjects = self::NOTE_SUBJECTS;
        $tagPools = self::TAG_POOLS;
        $ttlPresets = TtlPreset::cases();

        for ($i = 1; self::ITEM_COUNT >= $i; ++$i) {
            // $categories comes from load()'s runtime loop, so PHPStan can't
            // prove the modulo index in bounds the way it can for the
            // literal arrays below — the others don't need this guard.
            $category = $categories[$i % count($categories)] ?? throw new LogicException('Category index out of range — CATEGORY_NAMES must not be empty.');
            $subject = $subjects[$i % count($subjects)];

            // Mostly keepForever, so items survive TTL/GC while browsing —
            // every 5th one gets a real TTL instead, to also exercise
            // "expiring soon" (AdminController::expiring, ExpiringPage).
            $hasTtl = 0 === $i % 5;

            $ttlPreset = $hasTtl ? $ttlPresets[$i % count($ttlPresets)] : null;
            $tags = $tagPools[$i % count($tagPools)];

            $item = $this->itemService->createNote(
                categoryId: $category->getId(),
                content: sprintf('%s #%d', $subject, $i),
                options: new ItemLifecycleOptions(
                    name: null,
                    keepForever: !$hasTtl,
                    ttlPreset: $ttlPreset,
                    customExpiresAt: null,
                ),
                tags: $tags,
            );

            if (0 === $i % 4) {
                $this->itemService->setFavorite($item->getId(), true);
            }
        }
    }

    #[Override]
    public static function getGroups(): array
    {
        return ['default'];
    }

    // Goes through the real password_hashers config (security.yaml) via
    // Symfony's own hasher, same as every other User password in the app.
    private function createUser(string $email, #[SensitiveParameter] string $plainPassword, Pouch $pouch): User
    {
        $user = new User($email, '', $pouch);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        return $user;
    }
}
