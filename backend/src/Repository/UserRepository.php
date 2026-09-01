<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

use function in_array;
use function is_numeric;
use function sprintf;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    #[Override]
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function saveUser(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function remove(User $user): void
    {
        $this->getEntityManager()->remove($user);
        $this->getEntityManager()->flush();
    }

    public function findUserByEmail(string $email): ?User
    {
        return $this->findOneBy(
            criteria: [
                'email' => $email,
            ]
        );
    }

    /**
     * @return list<User>
     */
    public function findAllOrderedByEmail(): array
    {
        /** @var list<User> $result */
        $result = $this->createQueryBuilder('u')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Every account in one pouch — used by the self-service "delete my whole
     * pouch" to refuse when anyone besides the admin doing it still has an
     * account there (see PouchDeletionService).
     *
     * @return list<User>
     */
    public function findAllInPouch(int $pouchId): array
    {
        /** @var list<User> $result */
        $result = $this->createQueryBuilder('u')
            ->where('u.pouch = :pouchId')
            ->setParameter('pouchId', $pouchId)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * How many accounts system-wide have ROLE_ADMIN — filtered in PHP, not
     * SQL: `roles` is a JSON column and this app's account count is small
     * enough (hobby-scale) that a raw JSON-containment query would be
     * needless complexity for what's a one-off safety check.
     */
    public function countAdmins(): int
    {
        $count = 0;
        foreach ($this->findAllOrderedByEmail() as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Every account system-wide, across every pouch.
     */
    public function countAll(): int
    {
        $count = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return is_numeric($count) ? (int) $count : 0;
    }
}
